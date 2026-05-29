<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Diagnosis;
use App\Models\ClinicVisit;
use App\Support\RoleGate;
use Illuminate\Http\Request;

class DiagnosisController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Diagnosis::with(['student', 'nurse', 'clinicVisit'])->latest('diagnosed_at');
        $query->when($request->q, fn ($q, $v) => $q->where('title', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%"));
        $this->audit->log($request, 'diagnoses.index');
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    /**
     * Create a standalone diagnosis for an existing clinic visit.
     * Nurse-only. Allows adding a diagnosis after the visit was initially recorded.
     */
    public function store(Request $request)
    {
        RoleGate::nurse($request);

        $data = $request->validate([
            'clinic_visit_id' => 'required|exists:clinic_visits,id',
            'code'            => 'nullable|string|max:80',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'treatment_plan'  => 'nullable|string',
            'status'          => 'nullable|in:pending_checkup,under_evaluation,diagnosed,treated,referred,emergency',
        ]);

        $visit = ClinicVisit::findOrFail($data['clinic_visit_id']);
        $nurse = $this->nurseFor($request);

        $diagnosis = Diagnosis::create([
            'clinic_visit_id' => $visit->id,
            'student_id'      => $visit->student_id,
            'nurse_id'        => $nurse->id,
            'code'            => $data['code'] ?? null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'treatment_plan'  => $data['treatment_plan'] ?? null,
            'status'          => $data['status'] ?? 'diagnosed',
            'diagnosed_at'    => now(),
        ]);

        $this->audit->log($request, 'diagnosis.created', $diagnosis, [], $diagnosis->toArray());
        $this->events->publish(
            'DiagnosisUpdated',
            ['diagnosis_id' => $diagnosis->id, 'student_id' => $diagnosis->student_id],
            $request->header('X-Correlation-ID')
        );

        return $this->ok($diagnosis->load(['student', 'nurse', 'clinicVisit']), 'Diagnosis recorded.', 201);
    }

    public function show(Request $request, Diagnosis $diagnosis)
    {
        $this->audit->log($request, 'diagnosis.viewed', $diagnosis);
        return $this->ok($diagnosis->load(['student', 'nurse', 'clinicVisit']));
    }

    public function update(Request $request, Diagnosis $diagnosis)
    {
        RoleGate::nurse($request);
        $before = $diagnosis->toArray();
        $data = $request->validate([
            'code'           => 'nullable|string|max:80',
            'title'          => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'status'         => 'sometimes|in:pending_checkup,under_evaluation,diagnosed,treated,referred,emergency',
        ]);
        $diagnosis->update($data + ['diagnosed_at' => $diagnosis->diagnosed_at ?: now()]);
        $this->audit->log($request, 'diagnosis.updated', $diagnosis, $before, $diagnosis->toArray());
        $this->events->publish('DiagnosisUpdated', ['diagnosis_id' => $diagnosis->id, 'student_id' => $diagnosis->student_id], $request->header('X-Correlation-ID'));

        return $this->ok($diagnosis->fresh(['student', 'nurse']));
    }

    public function destroy(Request $request, Diagnosis $diagnosis)
    {
        RoleGate::nurse($request);
        $this->audit->log($request, 'diagnosis.deleted', $diagnosis, $diagnosis->toArray());
        $diagnosis->delete();
        return $this->ok([], 'Diagnosis deleted.');
    }
}
