<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public EmergencyAlert $alert) {}

    public function broadcastOn(): array
    {
        return [
            new Channel(config('meditrack.redis_channels.emergency_alerts')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'EmergencyAlertIssued';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'severity' => $this->alert->severity,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'issued_at' => $this->alert->issued_at?->toIso8601String(),
        ];
    }
}
