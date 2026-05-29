<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessInboundMediTrackEventJob;

/**
 * EventBusConsumer
 *
 * Listens to DEORIS event bus on Redis and processes inbound events.
 * Validates event signatures and delegates to appropriate handlers.
 */
class EventBusConsumer
{
    /**
     * Start consuming events from Redis.
     * This should be run as a long-lived process (e.g., queue worker or daemon).
     *
     * In production, use `php artisan queue:work` instead of calling this directly.
     */
    public function consume(): void
    {
        $channel = config('meditrack.event_bus.redis_channel', 'deoris.events');
        $redis = Redis::connection('default');

        Log::info('[MediTrack] Starting event bus consumer', ['channel' => $channel]);

        try {
            $redis->subscribe([$channel], function ($message, $channel) {
                $this->handleMessage($message);
            });
        } catch (\Exception $e) {
            Log::error('[MediTrack] Event bus consumer error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ]);
        }
    }

    /**
     * Handle incoming message from Redis pub/sub.
     */
    protected function handleMessage($message): void
    {
        try {
            if (!is_string($message)) {
                return;
            }

            $data = json_decode($message, true);
            if (!$data || !is_array($data)) {
                Log::warning('[MediTrack] Invalid event message format');
                return;
            }

            // Extract envelope
            $eventData = $data['data'] ?? [];
            $signature = $data['signature'] ?? '';
            $timestamp = $data['timestamp'] ?? time();
            $nonce = $data['nonce'] ?? '';
            $sourceModule = $data['source_module'] ?? 'Unknown';

            // Skip if not from trusted modules
            if (!$this->isTrustedModule($sourceModule)) {
                Log::warning('[MediTrack] Event from untrusted module', [
                    'source_module' => $sourceModule,
                ]);
                return;
            }

            // Verify signature
            if (!$this->verifySignature($signature, $timestamp, $nonce, json_encode($eventData), $sourceModule)) {
                Log::warning('[MediTrack] Event signature verification failed', [
                    'source_module' => $sourceModule,
                ]);
                return;
            }

            // Check replay window (5 minutes)
            $replayWindow = config('meditrack.event_bus.max_age_seconds', 300);
            if (abs(time() - $timestamp) > $replayWindow) {
                Log::warning('[MediTrack] Event outside replay window', [
                    'timestamp' => $timestamp,
                    'current_time' => time(),
                    'replay_window' => $replayWindow,
                ]);
                return;
            }

            // Process event
            $this->processEvent($eventData);
        } catch (\Exception $e) {
            Log::error('[MediTrack] Error consuming message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    /**
     * Verify event signature using module secret.
     */
    protected function verifySignature(string $signature, int $timestamp, string $nonce, string $body, string $sourceModule): bool
    {
        $trustedModules = config('meditrack.trusted_modules', []);
        $secret = $trustedModules[$sourceModule] ?? null;

        if (empty($secret)) {
            return false;
        }

        $message = $timestamp . '.' . $nonce . '.' . $body;
        $expected = hash_hmac('sha256', $message, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Check if module is trusted.
     */
    protected function isTrustedModule(string $module): bool
    {
        $trustedModules = config('meditrack.trusted_modules', []);

        return array_key_exists($module, $trustedModules);
    }

    /**
     * Process event by dispatching to appropriate job.
     */
    protected function processEvent(array $eventData): void
    {
        $eventName = $eventData['name'] ?? 'Unknown';

        // Only process events relevant to MediTrack
        $allowedEvents = ['StudentEnrolled', 'TuitionPaid', 'MedicalApproved'];

        if (!in_array($eventName, $allowedEvents)) {
            return;
        }

        // Dispatch job for async processing
        ProcessInboundMediTrackEventJob::dispatch($eventData)
            ->onQueue(config('meditrack.queue_names.events', 'events'));

        Log::info('[MediTrack] Event dispatched for processing', [
            'event_name' => $eventName,
            'correlation_id' => $eventData['correlation_id'] ?? null,
        ]);
    }
}
