<?php

namespace App\Jobs;

use App\Models\EventOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PublishMedicalEventJob implements ShouldQueue
{
    use Queueable;

    private $maxAttempts = 5;
    private $backoff = [5, 15, 45, 120, 300];

    public function __construct(public int $outboxId) {}

    public function handle(): void
    {
        $event = EventOutbox::findOrFail($this->outboxId);
        $event->increment('attempts');

        try {
            // Publish to local Redis channel
            Redis::publish(
                config('meditrack.redis_channels.medical_events'),
                json_encode($event->payload)
            );

            // Publish to DEORIS event hub if configured
            if (config('meditrack.event_hub_url')) {
                $timestamp = time();
                $nonce = $event->nonce;
                $body = json_encode($event->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $signature = hash_hmac(
                    'sha256',
                    $timestamp . '.' . $nonce . '.' . $body,
                    (string) config('meditrack.event_secret')
                );

                Http::withHeaders([
                    'X-DEORIS-Module' => config('meditrack.event_module'),
                    'X-DEORIS-Timestamp' => (string) $timestamp,
                    'X-DEORIS-Nonce' => $nonce,
                    'X-DEORIS-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])->withBody($body, 'application/json')
                    ->post(config('meditrack.event_hub_url'))
                    ->throw();
            }

            $event->update([
                'status' => 'published',
                'published_at' => now(),
                'last_error' => null,
            ]);

            Log::info('[MediTrack] Event published successfully', [
                'event_id' => $event->event_id,
                'event_name' => $event->event_name,
            ]);
        } catch (\Throwable $e) {
            $error = substr($e->getMessage(), 0, 500);
            $event->update(['status' => 'failed', 'last_error' => $error]);

            Log::error('[MediTrack] Event publishing failed', [
                'event_id' => $event->event_id,
                'attempts' => $event->attempts,
                'error' => $error,
            ]);

            throw $e;
        }
    }

    public function backoff(): array
    {
        return $this->backoff;
    }

    public function tries(): int
    {
        return $this->maxAttempts;
    }

    public function failed(\Throwable $exception): void
    {
        $event = EventOutbox::find($this->outboxId);
        if ($event) {
            $event->update(['status' => 'failed']);
            Log::error('[MediTrack] Event publishing permanently failed', [
                'event_id' => $event->event_id,
                'max_attempts' => $this->maxAttempts,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
