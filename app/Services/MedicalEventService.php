<?php

namespace App\Services;

use App\Jobs\PublishMedicalEventJob;
use App\Models\EventOutbox;
use Illuminate\Support\Str;

class MedicalEventService
{
    public function publish(string $eventName, array $payload, ?string $correlationId = null): EventOutbox
    {
        $event = [
            'event_id' => (string) Str::uuid(),
            'event_name' => $eventName,
            'source_service' => config('meditrack.service_key'),
            'id' => null,
            'name' => $eventName,
            'source_module' => config('meditrack.event_module'),
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
            'occurred_at' => now()->toAtomString(),
            'schema_version' => config('meditrack.event_schema_version'),
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
        ];
        $event['id'] = $event['event_id'];

        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();
        $body = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, (string) config('meditrack.event_secret'));

        $outbox = EventOutbox::create([
            'event_id' => $event['event_id'],
            'event_name' => $eventName,
            'source_service' => $event['source_service'],
            'payload' => $event,
            'signature' => $signature,
            'nonce' => $nonce,
            'schema_version' => $event['schema_version'],
            'correlation_id' => $event['correlation_id'],
            'status' => 'pending',
        ]);

        PublishMedicalEventJob::dispatch($outbox->id)->onQueue(config('meditrack.queue_names.events'));

        return $outbox;
    }
}
