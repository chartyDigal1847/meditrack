<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyAlert extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'clinic_visit_id', 'nurse_id', 'alert_code', 'severity', 'title', 'message', 'status', 'issued_at', 'resolved_at'];
    protected $casts = ['issued_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
