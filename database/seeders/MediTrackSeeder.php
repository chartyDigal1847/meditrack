<?php

namespace Database\Seeders;

use App\Models\ClinicVisit;
use App\Models\Diagnosis;
use App\Models\EmergencyAlert;
use App\Models\HealthReport;
use App\Models\MedicalRecord;
use App\Models\Nurse;
use App\Models\Prescription;
use App\Models\Student;
use App\Models\StudentConcern;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MediTrackSeeder extends Seeder
{
    public function run(): void
    {
        $nurse = Nurse::firstOrCreate(
            ['external_id' => 'nurse-demo-001'],
            ['name' => 'Maria Santos, RN', 'email' => 'maria.santos@deoris.local', 'license_number' => 'RN-2026-001']
        );

        $students = collect([
            ['2026-0001', 'Alyssa', 'Reyes', 'Grade 7', 'St. Anne'],
            ['2026-0002', 'Marco', 'Dela Cruz', 'Grade 8', 'St. Luke'],
            ['2026-0003', 'Sofia', 'Garcia', 'Grade 9', 'St. Mark'],
        ])->map(fn ($s) => Student::firstOrCreate(
            ['student_number' => $s[0]],
            ['first_name' => $s[1], 'last_name' => $s[2], 'grade_level' => $s[3], 'section' => $s[4], 'email' => strtolower($s[1]).'@student.deoris.local']
        ));

        foreach ($students as $index => $student) {
            $visit = ClinicVisit::firstOrCreate(
                ['visit_code' => 'VIS-DEMO-'.($index + 1)],
                [
                    'student_id' => $student->id,
                    'nurse_id' => $nurse->id,
                    'chief_complaint' => ['Headache and dizziness', 'Mild fever', 'Sports ankle strain'][$index],
                    'visit_type' => 'walk_in',
                    'status' => ['under_evaluation', 'diagnosed', 'treated'][$index],
                    'severity' => ['medium', 'high', 'low'][$index],
                    'temperature' => [37.2, 38.3, 36.8][$index],
                    'blood_pressure' => ['110/70', '115/75', '118/76'][$index],
                    'pulse_rate' => [84, 92, 78][$index],
                    'weight_kg' => [43.5, 49.2, 51.0][$index],
                    'notes' => 'Demo clinic visit for MediTrack workflow validation.',
                    'checked_in_at' => now()->subDays($index),
                ]
            );

            Diagnosis::firstOrCreate(
                ['clinic_visit_id' => $visit->id, 'title' => ['Tension headache', 'Febrile episode', 'Minor sprain'][$index]],
                ['student_id' => $student->id, 'nurse_id' => $nurse->id, 'description' => 'Initial school clinic diagnosis.', 'treatment_plan' => 'Rest, hydration, and monitoring.', 'diagnosed_at' => now()->subDays($index)]
            );

            MedicalRecord::firstOrCreate(
                ['student_id' => $student->id, 'title' => 'Clinic encounter summary'],
                ['nurse_id' => $nurse->id, 'record_type' => 'clinic_note', 'summary' => 'Visit reviewed and documented by school nurse.', 'sensitive_notes' => 'Confidential nursing notes.', 'approved_at' => now()]
            );

            Prescription::firstOrCreate(
                ['student_id' => $student->id, 'medication_name' => ['Paracetamol', 'Oral rehydration salts', 'Cold compress'][$index]],
                ['clinic_visit_id' => $visit->id, 'nurse_id' => $nurse->id, 'dosage' => ['500 mg', '1 sachet', '15 minutes'][$index], 'frequency' => ['as needed', 'after each loose stool', 'every 2 hours'][$index], 'instructions' => 'Follow school clinic protocol.', 'issued_at' => now()]
            );
        }

        EmergencyAlert::firstOrCreate(
            ['alert_code' => 'ALERT-DEMO-001'],
            ['student_id' => $students[1]->id, 'nurse_id' => $nurse->id, 'severity' => 'high', 'title' => 'Fever monitoring required', 'message' => 'Guardian notification and observation recommended.', 'issued_at' => now()]
        );

        HealthReport::firstOrCreate(
            ['title' => 'Weekly Clinic Utilization'],
            ['nurse_id' => $nurse->id, 'report_type' => 'weekly_analytics', 'period_start' => now()->startOfWeek(), 'period_end' => now()->endOfWeek(), 'summary' => 'Weekly summary of clinic visits, diagnoses, and alerts.', 'metrics' => ['visits' => 3, 'alerts' => 1], 'generated_at' => now()]
        );

        StudentConcern::firstOrCreate(
            ['external_student_id' => 'student-demo-001', 'title' => 'Recurring headache'],
            ['description' => 'Student reported recurring headache after lunch.', 'severity' => 'medium', 'submitted_at' => now()]
        );
    }
}
