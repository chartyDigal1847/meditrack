<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = ['recipient_external_id', 'recipient_role', 'type', 'title', 'message', 'data', 'read_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
