<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalAuditLog extends Model
{
    protected $fillable = ['actor_external_id', 'actor_name', 'actor_role', 'action', 'auditable_type', 'auditable_id', 'ip_address', 'user_agent', 'before', 'after', 'correlation_id'];
    protected $casts = ['before' => 'array', 'after' => 'array'];
}
