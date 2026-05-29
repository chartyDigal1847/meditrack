<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ClinicVisit;
use App\Models\Student;
use App\Support\RoleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClinicVisitController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = ClinicVisit::with(['student', 'nurse', 'diagnoses'])->latest('checked_in_at');
        $query->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $query->when($request->severity, fn ($q, $v) => $q->where('severity', $v));
        $query->when($request->q, fn ($q, $v) => $q->whereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%")->orWhere('student_number', 'like', "%{$v}%")));

        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            $query->where('student_id', $studentId);
        }

        $this->audit->log($request, 'clinic_visits.index');

        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        RoleGate::nurse($request);
        $this->normalizeVisitInput($request);

        $data = $request->validate([
            'student_id' => 'nullable|string|max:255', // could be local id, external id, email or student_number
            'student_number' => 'nullable|string|max:80',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:160',
            'grade_level' => 'nullable|string|max:60',
            'section' => 'nullable|string|max:60',
            'chief_complaint' => 'required|string|max:255',
            'visit_type' => 'nullable|string|max:80',
            'status' => 'nullable|in:pending_checkup,under_evaluation,diagnosed,treated,referred,emergency',
            'severity' => 'nullable|in:low,medium,high,emergency',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'blood_pressure' => 'nullable|string|max:30',
            'pulse_rate' => 'nullable|integer|min:20|max:240',
            'respiratory_rate' => 'nullable|integer|min:5|max:80',
            'weight_kg' => 'nullable|numeric|min:1|max:300',
            'notes' => 'nullable|string',
            'diagnosis_title' => 'nullable|string|max:255',
            'diagnosis_description' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
        ]);

        // Resolve or create a local Student record. Accept either `student_id` (from
        // selector) or `student_number`. If `student_id` refers to an external
        // portal id/email, attempt to resolve to a local Student; if not found,
        // create a local placeholder Student with `external_id` set so future
        // inbound sync can reconcile.

        $identifier = $data['student_id'] ?? $data['student_number'] ?? null;
        $student = null;
        if ($identifier) {
            $student = $this->resolveStudent($identifier);
        }

        if (! $student) {
            if ($identifier) {
                // Create a local placeholder student linked by external_id.
                $student = Student::firstOrCreate(
                    ['external_id' => (string) $identifier],
                    [
                        'student_number' => 'EXT-' . substr((string) $identifier, 0, 60),
                        'first_name' => $data['first_name'] ?? 'Student',
                        'last_name' => $data['last_name'] ?? '',
                        'email' => $data['email'] ?? null,
                    ]
                );
            } else {
                // Fallback: require student_number and names when no identifier.
                $fallback = $request->validate([
                    'student_number' => 'required|string|max:80',
                    'first_name' => 'required|string|max:120',
                    'last_name' => 'required|string|max:120',
                ]);

                $student = Student::updateOrCreate(
                    ['student_number' => $fallback['student_number']],
                    [
                        'first_name' => $fallback['first_name'],
                        'last_name' => $fallback['last_name'],
                        'email' => $data['email'] ?? null,
                        'grade_level' => $data['grade_level'] ?? null,
                        'section' => $data['section'] ?? null,
                    ]
                );
            }
        }

        $nurse = $this->nurseFor($request);
        $visit = ClinicVisit::create([
            'student_id' => $student->id,
            'nurse_id' => $nurse->id,
            'visit_code' => 'VIS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'chief_complaint' => $data['chief_complaint'],
            'visit_type' => $data['visit_type'] ?? 'walk_in',
            'status' => $data['status'] ?? 'under_evaluation',
            'severity' => $data['severity'] ?? 'low',
            'temperature' => $data['temperature'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'pulse_rate' => $data['pulse_rate'] ?? null,
            'respiratory_rate' => $data['respiratory_rate'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'notes' => $data['notes'] ?? null,
            'checked_in_at' => now(),
        ]);

        if (! empty($data['diagnosis_title'])) {
            $visit->diagnoses()->create([
                'student_id' => $student->id,
                'nurse_id' => $nurse->id,
                'title' => $data['diagnosis_title'],
                'description' => $data['diagnosis_description'] ?? null,
                'treatment_plan' => $data['treatment_plan'] ?? null,
                'diagnosed_at' => now(),
            ]);
        }

        $this->audit->log($request, 'clinic_visit.recorded', $visit, [], $visit->toArray());
        $this->events->publish('ClinicVisitRecorded', ['clinic_visit_id' => $visit->id, 'student_id' => $student->id], $request->header('X-Correlation-ID'));

        return $this->ok($visit->load(['student', 'nurse', 'diagnoses']), 'Clinic visit recorded.', 201);
    }

    public function show(Request $request, ClinicVisit $clinicVisit)
    {
        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            if ($clinicVisit->student_id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this clinic visit.',
                ], 403);
            }
        }

        $this->audit->log($request, 'clinic_visit.viewed', $clinicVisit);
        return $this->ok($clinicVisit->load(['student', 'nurse', 'diagnoses', 'prescriptions']));
    }

    public function update(Request $request, ClinicVisit $clinicVisit)
    {
        RoleGate::nurse($request);
        $this->normalizeVisitInput($request);

        $before = $clinicVisit->toArray();
        $data = $request->validate([
            'chief_complaint' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|in:pending_checkup,under_evaluation,diagnosed,treated,referred,emergency',
            'severity' => 'sometimes|in:low,medium,high,emergency',
            'notes' => 'nullable|string',
            'checked_out_at' => 'nullable|date',
        ]);
        $clinicVisit->update($data);
        $this->audit->log($request, 'clinic_visit.updated', $clinicVisit, $before, $clinicVisit->toArray());
        $this->events->publish('HealthRecordUpdated', ['clinic_visit_id' => $clinicVisit->id], $request->header('X-Correlation-ID'));

        return $this->ok($clinicVisit->fresh(['student', 'nurse', 'diagnoses']));
    }

    private function normalizeVisitInput(Request $request): void
    {
        $input = collect($request->all())->map(function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        })->all();

        foreach (['status', 'severity'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = Str::of($input[$field])
                    ->lower()
                    ->replace([' ', '-'], '_')
                    ->toString();
            }
        }

        $request->merge($input);
    }

    public function destroy(Request $request, ClinicVisit $clinicVisit)
    {
        RoleGate::nurse($request);
        $this->audit->log($request, 'clinic_visit.deleted', $clinicVisit, $clinicVisit->toArray());
        $clinicVisit->delete();

        return $this->ok([], 'Clinic visit deleted.');
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }
}
