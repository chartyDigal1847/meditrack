<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = ['external_id', 'student_number', 'first_name', 'last_name', 'email', 'grade_level', 'section', 'birthdate', 'guardian_name', 'guardian_contact', 'emergency_contact', 'medical_flags', 'synced_at'];
    protected $casts = ['birthdate' => 'date', 'medical_flags' => 'array', 'synced_at' => 'datetime'];

    public function clinicVisits(): HasMany { return $this->hasMany(ClinicVisit::class); }
    public function medicalRecords(): HasMany { return $this->hasMany(MedicalRecord::class); }
}
