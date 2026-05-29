<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diagnosis extends Model
{
    use SoftDeletes;

    protected $fillable = ['clinic_visit_id', 'student_id', 'nurse_id', 'code', 'title', 'description', 'treatment_plan', 'status', 'diagnosed_at'];
    protected $casts = ['diagnosed_at' => 'datetime'];

    public function clinicVisit(): BelongsTo { return $this->belongsTo(ClinicVisit::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function nurse(): BelongsTo { return $this->belongsTo(Nurse::class); }
}
