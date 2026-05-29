<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use SoftDeletes;

    protected $fillable = ['clinic_visit_id', 'student_id', 'nurse_id', 'medication_name', 'dosage', 'frequency', 'duration', 'instructions', 'status', 'issued_at'];
    protected $casts = ['issued_at' => 'datetime'];

    public function clinicVisit(): BelongsTo { return $this->belongsTo(ClinicVisit::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
