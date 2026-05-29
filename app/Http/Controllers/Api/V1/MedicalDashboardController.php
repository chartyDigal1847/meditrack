<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\ClinicVisit;
use App\Models\Diagnosis;
use App\Models\EmergencyAlert;
use App\Models\HealthReport;
use App\Models\MedicalAuditLog;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\Prescription;
use App\Models\Student;
use App\Models\StudentConcern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicalDashboardController extends BaseApiController
{
    public function bootstrap(Request $request)
    {
        $this->audit->log($request, 'dashboard.bootstrap');

        $role = $this->role($request);
        $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));

        if ($role === 'student') {
            $student = Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;

            return $this->ok([
                'service' => [
                    'service_name' => config('meditrack.service_name'),
                    'service_key' => config('meditrack.service_key'),
                    'api_version' => config('meditrack.api_version'),
                    'redis_channels' => config('meditrack.redis_channels'),
                    'queue_names' => config('meditrack.queue_names'),
                ],
                'role' => $role,
                'metrics' => $this->metrics($request),
                'clinic_visits' => ClinicVisit::with(['student', 'nurse', 'diagnoses'])->where('student_id', $studentId)->latest()->limit(12)->get(),
                'records' => MedicalRecord::with(['student', 'nurse'])->where('student_id', $studentId)->latest()->limit(10)->get(),
                'alerts' => collect(),
                'reports' => HealthReport::where(fn($q) => $q->where('student_id', $studentId)->orWhereNull('student_id'))->latest()->limit(8)->get(),
                'prescriptions' => Prescription::with('student')->where('student_id', $studentId)->latest()->limit(8)->get(),
                'notifications' => collect(),
                'concerns' => StudentConcern::where('external_student_id', $ssoId)->latest()->limit(10)->get(),
                'audit_logs' => collect(),
                'activity' => collect(),
                'diagnosis_trends' => Diagnosis::select('title', DB::raw('count(*) as total'))->groupBy('title')->orderByDesc('total')->limit(8)->get(),
            ]);
        }

        return $this->ok([
            'service' => [
                'service_name' => config('meditrack.service_name'),
                'service_key' => config('meditrack.service_key'),
                'api_version' => config('meditrack.api_version'),
                'redis_channels' => config('meditrack.redis_channels'),
                'queue_names' => config('meditrack.queue_names'),
            ],
            'role' => $role,
            'metrics' => $this->metrics($request),
            'clinic_visits' => ClinicVisit::with(['student', 'nurse', 'diagnoses'])->latest()->limit(12)->get(),
            'records' => MedicalRecord::with(['student', 'nurse'])->latest()->limit(10)->get(),
            'alerts' => EmergencyAlert::with('student')->latest()->limit(10)->get(),
            'reports' => HealthReport::latest()->limit(8)->get(),
            'prescriptions' => Prescription::with('student')->latest()->limit(8)->get(),
            'notifications' => Notification::latest()->limit(10)->get(),
            'concerns' => StudentConcern::latest()->limit(10)->get(),
            'audit_logs' => MedicalAuditLog::latest()->limit(12)->get(),
            'activity' => ActivityLog::orderByDesc('at')->limit(12)->get(),
            'diagnosis_trends' => Diagnosis::select('title', DB::raw('count(*) as total'))->groupBy('title')->orderByDesc('total')->limit(8)->get(),
        ]);
    }

    public function analytics(Request $request)
    {
        $this->audit->log($request, 'analytics.view');

        return $this->ok([
            'metrics' => $this->metrics($request),
            'visits_by_status' => ClinicVisit::select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            'alerts_by_severity' => EmergencyAlert::select('severity', DB::raw('count(*) as total'))->groupBy('severity')->get(),
            'nurse_activity' => ClinicVisit::query()
                ->leftJoin('nurses', 'nurses.id', '=', 'clinic_visits.nurse_id')
                ->select('nurses.name', DB::raw('count(clinic_visits.id) as visits'))
                ->groupBy('nurses.name')
                ->orderByDesc('visits')
                ->limit(10)
                ->get(),
        ]);
    }

    private function metrics(Request $request): array
    {
        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;

            return [
                'records' => MedicalRecord::where('student_id', $studentId)->count(),
                'diagnoses' => Diagnosis::where('student_id', $studentId)->count(),
                'prescriptions' => Prescription::where('student_id', $studentId)->count(),
            ];
        }

        return [
            'students' => Student::count(),
            'visits_today' => ClinicVisit::whereDate('checked_in_at', today())->count(),
            'open_visits' => ClinicVisit::whereIn('status', ['pending_checkup', 'under_evaluation', 'emergency'])->count(),
            'diagnoses' => Diagnosis::count(),
            'records' => MedicalRecord::count(),
            'prescriptions' => Prescription::count(),
            'active_alerts' => EmergencyAlert::where('status', 'active')->count(),
            'pending_concerns' => StudentConcern::where('status', 'pending_checkup')->count(),
        ];
    }
}
