<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class ClinicVisit extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'nurse_id', 'visit_code', 'chief_complaint', 'visit_type', 'status', 'severity', 'temperature', 'blood_pressure', 'pulse_rate', 'respiratory_rate', 'weight_kg', 'notes', 'checked_in_at', 'checked_out_at'];
    protected $casts = ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'temperature' => 'decimal:1', 'weight_kg' => 'decimal:2'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function nurse(): BelongsTo { return $this->belongsTo(Nurse::class); }
    public function diagnoses(): HasMany { return $this->hasMany(Diagnosis::class); }
    public function prescriptions(): HasMany { return $this->hasMany(Prescription::class); }

    protected static function booted()
    {
        static::deleting(function (ClinicVisit $visit) {
            // If soft-deleting, cascade soft-delete to related children so data is hidden consistently.
            if (! method_exists($visit, 'isForceDeleting') || ! $visit->isForceDeleting()) {
                $visit->diagnoses()->delete();
                $visit->prescriptions()->delete();
            } else {
                // Permanent delete — remove children permanently.
                $visit->diagnoses()->withTrashed()->forceDelete();
                $visit->prescriptions()->withTrashed()->forceDelete();
            }
        });

        static::restoring(function (ClinicVisit $visit) {
            // Restore children when a visit is restored.
            $visit->diagnoses()->withTrashed()->restore();
            $visit->prescriptions()->withTrashed()->restore();
        });
    }
}
