<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Prescription;
use App\Support\RoleGate;
use Illuminate\Http\Request;

class PrescriptionController extends BaseApiController
{
    public function index(Request $request)
    {
        $role = $this->role($request);
        $query = Prescription::with(['student', 'clinicVisit'])->latest();
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            $query->where('student_id', $studentId);
        }
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function show(Request $request, Prescription $prescription)
    {
        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            if ($prescription->student_id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this prescription.',
                ], 403);
            }
        }

        $this->audit->log($request, 'prescription.viewed', $prescription);
        return $this->ok($prescription->load(['student', 'clinicVisit']));
    }

    public function store(Request $request)
    {
        RoleGate::nurse($request);
        $data = $request->validate([
            'student_id' => 'nullable|string|max:255',
            'clinic_visit_id' => 'nullable|exists:clinic_visits,id',
            'medication_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:120',
            'frequency' => 'required|string|max:120',
            'duration' => 'nullable|string|max:120',
            'instructions' => 'nullable|string',
            'status' => 'nullable|string|max:80',
        ]);

        // Resolve or create a local Student record.
        $identifier = $data['student_id'] ?? null;
        $student = null;
        if ($identifier) {
            $student = $this->resolveStudent($identifier);
        }

        if (! $student && $identifier) {
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

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student identifier is required.'], 422);
        }

        $prescription = Prescription::create($data + ['student_id' => $student->id, 'nurse_id' => $this->nurseFor($request)->id, 'issued_at' => now()]);
        $this->audit->log($request, 'prescription.created', $prescription, [], $prescription->toArray());
        $this->events->publish('HealthRecordUpdated', ['prescription_id' => $prescription->id, 'student_id' => $prescription->student_id], $request->header('X-Correlation-ID'));
        return $this->ok($prescription->load('student'), 'Prescription created.', 201);
    }

    public function update(Request $request, Prescription $prescription)
    {
        RoleGate::nurse($request);
        $before = $prescription->toArray();
        $data = $request->validate(['medication_name' => 'sometimes|required|string|max:255', 'dosage' => 'sometimes|required|string|max:120', 'frequency' => 'sometimes|required|string|max:120', 'duration' => 'nullable|string|max:120', 'instructions' => 'nullable|string', 'status' => 'nullable|string|max:80']);
        $prescription->update($data);
        $this->audit->log($request, 'prescription.updated', $prescription, $before, $prescription->toArray());
        return $this->ok($prescription->fresh('student'));
    }

    public function destroy(Request $request, Prescription $prescription)
    {
        RoleGate::nurse($request);
        $prescription->delete();
        $this->audit->log($request, 'prescription.deleted', $prescription);
        return $this->ok([], 'Prescription deleted.');
    }
}
