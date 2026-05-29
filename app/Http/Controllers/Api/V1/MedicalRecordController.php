<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\MedicalRecord;
use App\Support\RoleGate;
use Illuminate\Http\Request;

class MedicalRecordController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = MedicalRecord::with(['student', 'nurse'])->latest();
        $query->when($request->record_type, fn ($q, $v) => $q->where('record_type', $v));
        $query->when($request->q, fn ($q, $v) => $q->where('title', 'like', "%{$v}%")->orWhere('summary', 'like', "%{$v}%"));

        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            $query->where('student_id', $studentId);
        }

        $this->audit->log($request, 'medical_records.index');
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        RoleGate::nurse($request);
        $data = $request->validate([
            'student_id' => 'nullable|string|max:255',
            'record_type' => 'required|string|max:80',
            'title' => 'required|string|max:255',
            'summary' => 'required|string',
            'sensitive_notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'status' => 'nullable|string|max:80',
        ]);

        // Resolve or create a local Student record. Accept student_id (from dropdown).
        // If student_id refers to an external portal id/email, attempt to resolve to local;
        // if not found, create a local placeholder with external_id set.
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

        $record = MedicalRecord::create($data + ['student_id' => $student->id, 'nurse_id' => $this->nurseFor($request)->id, 'approved_at' => now()]);
        $this->audit->log($request, 'medical_record.created', $record, [], $record->toArray());
        $record->load('student');
        $this->events->publish('MedicalApproved', [
            'medical_record_id' => $record->id,
            'student_id' => $record->student_id,
            'student_number' => $record->student?->student_number,
            'student_email' => $record->student?->email,
        ], $request->header('X-Correlation-ID'));
        return $this->ok($record->load(['student', 'nurse']), 'Medical record created.', 201);
    }

    public function show(Request $request, MedicalRecord $medicalRecord)
    {
        $role = $this->role($request);
        if ($role === 'student') {
            $ssoId = $request->attributes->get('sso_id') ?: ($request->user()?->external_id ?: session('sso_id'));
            $student = \App\Models\Student::where('external_id', $ssoId)->first();
            $studentId = $student ? $student->id : 0;
            if ($medicalRecord->student_id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this medical record.',
                ], 403);
            }
        }

        $this->audit->log($request, 'medical_record.viewed', $medicalRecord);
        return $this->ok($medicalRecord->load(['student', 'nurse']));
    }

    public function update(Request $request, MedicalRecord $medicalRecord)
    {
        RoleGate::nurse($request);
        $before = $medicalRecord->toArray();
        $data = $request->validate([
            'record_type' => 'sometimes|required|string|max:80',
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'sometimes|required|string',
            'sensitive_notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'status' => 'nullable|string|max:80',
        ]);
        $medicalRecord->update($data);
        $this->audit->log($request, 'medical_record.updated', $medicalRecord, $before, $medicalRecord->toArray());
        $medicalRecord->load('student');
        $this->events->publish('HealthRecordUpdated', [
            'medical_record_id' => $medicalRecord->id,
            'student_id' => $medicalRecord->student_id,
            'student_number' => $medicalRecord->student?->student_number,
            'student_email' => $medicalRecord->student?->email,
        ], $request->header('X-Correlation-ID'));
        return $this->ok($medicalRecord->fresh(['student', 'nurse']));
    }

    public function destroy(Request $request, MedicalRecord $medicalRecord)
    {
        RoleGate::nurse($request);
        $this->audit->log($request, 'medical_record.deleted', $medicalRecord, $medicalRecord->toArray());
        $medicalRecord->delete();
        return $this->ok([], 'Medical record deleted.');
    }
}
