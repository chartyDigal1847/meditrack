<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeorisEventInbox extends Model
{
    protected $table = 'deoris_event_inbox';
    protected $fillable = [
        'event_id', 'event_name', 'source_module', 'payload',
        'signature', 'nonce', 'timestamp', 'correlation_id',
        'status', 'processed_at', 'error_message'
    ];
    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get pending inbound events.
     */
    public static function getPending()
    {
        return static::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get failed inbound events.
     */
    public static function getFailed()
    {
        return static::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark event as processed.
     */
    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark event as failed with error message.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => substr($error, 0, 1000),
        ]);
    }
}
