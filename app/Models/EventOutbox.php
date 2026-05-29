<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOutbox extends Model
{
    protected $table = 'event_outbox';
    protected $fillable = ['event_id', 'event_name', 'source_service', 'payload', 'signature', 'nonce', 'schema_version', 'correlation_id', 'status', 'published_at', 'attempts', 'last_error'];
    protected $casts = ['payload' => 'array', 'published_at' => 'datetime'];

    /**
     * Mark event as successfully published.
     */
    public function markPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark event as failed.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => substr($error, 0, 500),
        ]);
    }

    /**
     * Get pending events ready for publishing.
     */
    public static function getPending()
    {
        return static::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get failed events.
     */
    public static function getFailed()
    {
        return static::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
