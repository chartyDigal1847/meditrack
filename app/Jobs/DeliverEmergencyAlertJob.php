<?php

namespace App\Jobs;

use App\Events\EmergencyAlertBroadcasted;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class DeliverEmergencyAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alertId) {}

    public function handle(): void
    {
        $alert = EmergencyAlert::with('student')->findOrFail($this->alertId);

        foreach (['nurse', 'admin', 'student'] as $role) {
            Notification::create([
                'recipient_external_id' => $role === 'student' ? $alert->student?->external_id : null,
                'recipient_role' => $role,
                'type' => 'emergency_alert',
                'title' => $alert->title,
                'message' => $alert->message,
                'data' => ['alert_id' => $alert->id, 'severity' => $alert->severity],
            ]);
        }

        Redis::publish(config('meditrack.redis_channels.emergency_alerts'), json_encode([
            'alert_id' => $alert->id,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'issued_at' => $alert->issued_at?->toIso8601String(),
        ]));

        broadcast(new EmergencyAlertBroadcasted($alert));
    }
}
