<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['message', 'type', 'at'];

    protected $casts = [
        'at' => 'datetime',
    ];

    /**
     * Record an activity log entry and keep only the 50 most recent.
     */
    public static function record(string $message, string $type = 'gray'): void
    {
        static::create([
            'message' => $message,
            'type'    => $type,
            'at'      => now(),
        ]);

        // Keep only the 50 most recent entries
        $oldest = static::orderByDesc('at')->skip(50)->first();
        if ($oldest) {
            static::where('at', '<=', $oldest->at)->delete();
        }
    }
}
