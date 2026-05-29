<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\StudentConcern;
use App\Support\RoleGate;
use Illuminate\Http\Request;

class StudentConcernController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = StudentConcern::latest('submitted_at');
        $query->when($request->status, fn ($q, $v) => $q->where('status', $v));

        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $query->where('external_student_id', $ssoId);
        }

        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'nullable|in:low,medium,high',
            'student_id' => 'nullable|string|max:255',
        ]);

        $role = $this->role($request);
        $studentId = null;
        $externalStudentId = null;

        if ($role === 'nurse') {
            $identifier = $data['student_id'] ?? null;
            if (! $identifier) {
                return response()->json(['success' => false, 'message' => 'Student identifier is required for nurses.'], 422);
            }

            $student = $this->resolveStudent($identifier);
            if (! $student) {
                // Create placeholder student for external identifier
                $student = \App\Models\Student::firstOrCreate(
                    ['external_id' => (string) $identifier],
                    [
                        'student_number' => 'EXT-' . substr((string) $identifier, 0, 60),
                        'first_name' => 'Student',
                        'last_name' => '',
                    ]
                );
            }
            $studentId = $student->id;
            $externalStudentId = $student->external_id;
        } else {
            $externalStudentId = $request->user()?->external_id ?: session('sso_id');
            $student = \App\Models\Student::where('external_id', $externalStudentId)->first();
            $studentId = $student ? $student->id : null;
        }

        $concern = StudentConcern::create([
            'student_id' => $studentId,
            'external_student_id' => $externalStudentId,
            'title' => $data['title'],
            'description' => $data['description'],
            'severity' => $data['severity'] ?? 'low',
            'status' => 'pending_checkup',
            'submitted_at' => now(),
        ]);

        $this->audit->log($request, 'student_concern.submitted', $concern, [], $concern->toArray());
        return $this->ok($concern, 'Health concern submitted.', 201);
    }

    public function show(Request $request, StudentConcern $studentConcern) { return $this->ok($studentConcern); }

    public function update(Request $request, StudentConcern $studentConcern)
    {
        RoleGate::nurse($request);
        $data = $request->validate(['status' => 'required|in:pending_checkup,under_evaluation,treated,referred,emergency']);
        $studentConcern->update($data + ['reviewed_at' => now()]);
        $this->audit->log($request, 'student_concern.reviewed', $studentConcern);
        return $this->ok($studentConcern);
    }
}
