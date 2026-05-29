<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecord extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'nurse_id', 'record_type', 'title', 'summary', 'sensitive_notes', 'attachments', 'status', 'approved_at'];
    protected $casts = ['attachments' => 'array', 'approved_at' => 'datetime', 'sensitive_notes' => 'encrypted'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function nurse(): BelongsTo { return $this->belongsTo(Nurse::class); }
}
