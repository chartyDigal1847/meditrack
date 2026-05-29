<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HealthReport;
use App\Support\RoleGate;
use Illuminate\Http\Request;

class HealthReportController extends BaseApiController
{
    public function index(Request $request)
    {
        $this->audit->log($request, 'health_reports.index');
        $role = $this->role($request);
        $query = HealthReport::with('student')->latest('generated_at');
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            $query->where(fn($q) => $q->where('student_id', $studentId)->orWhereNull('student_id'));
        }
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function show(Request $request, HealthReport $healthReport)
    {
        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            if ($healthReport->student_id !== null && $healthReport->student_id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this health report.',
                ], 403);
            }
        }
        $this->audit->log($request, 'health_report.viewed', $healthReport);
        return $this->ok($healthReport->load('student'));
    }

    public function store(Request $request)
    {
        RoleGate::nurse($request);
        $data = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'report_type' => 'required|string|max:80',
            'title' => 'required|string|max:255',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'summary' => 'required|string',
            'metrics' => 'nullable|array',
            'status' => 'nullable|string|max:80',
        ]);
        $report = HealthReport::create($data + ['nurse_id' => $this->nurseFor($request)->id, 'generated_at' => now()]);
        $this->audit->log($request, 'health_report.created', $report, [], $report->toArray());
        return $this->ok($report, 'Health report generated.', 201);
    }

    public function update(Request $request, HealthReport $healthReport)
    {
        RoleGate::nurse($request);
        $before = $healthReport->toArray();
        $data = $request->validate(['report_type' => 'sometimes|required|string|max:80', 'title' => 'sometimes|required|string|max:255', 'summary' => 'sometimes|required|string', 'metrics' => 'nullable|array', 'status' => 'nullable|string|max:80']);
        $healthReport->update($data);
        $this->audit->log($request, 'health_report.updated', $healthReport, $before, $healthReport->toArray());
        return $this->ok($healthReport->fresh('student'));
    }

    public function destroy(Request $request, HealthReport $healthReport)
    {
        RoleGate::nurse($request);
        $healthReport->delete();
        $this->audit->log($request, 'health_report.deleted', $healthReport);
        return $this->ok([], 'Health report deleted.');
    }
}
