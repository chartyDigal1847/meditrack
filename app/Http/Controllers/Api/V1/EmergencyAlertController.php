<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\DeliverEmergencyAlertJob;
use App\Models\EmergencyAlert;
use App\Support\RoleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmergencyAlertController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = EmergencyAlert::with('student')->latest('issued_at');
        $query->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $query->when($request->severity, fn ($q, $v) => $q->where('severity', $v));
        $this->audit->log($request, 'emergency_alerts.index');
        return $this->ok($query->paginate((int) $request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        RoleGate::nurse($request);
        $data = $request->validate([
            'student_id' => 'nullable|string|max:255',
            'clinic_visit_id' => 'nullable|exists:clinic_visits,id',
            'severity' => 'required|in:high,critical,emergency',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'nullable|string|max:80',
        ]);

        // Resolve or create a local Student record.
        $student = null;
        if (!empty($data['student_id'])) {
            $identifier = $data['student_id'];
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
            $data['student_id'] = $student->id;
        }

        $alert = EmergencyAlert::create($data + ['nurse_id' => $this->nurseFor($request)->id, 'alert_code' => 'ALERT-'.Str::upper(Str::random(8)), 'issued_at' => now()]);
        $this->audit->log($request, 'emergency_alert.issued', $alert, [], $alert->toArray());
        $this->events->publish('EmergencyAlertIssued', ['emergency_alert_id' => $alert->id, 'student_id' => $alert->student_id], $request->header('X-Correlation-ID'));
        DeliverEmergencyAlertJob::dispatch($alert->id)->onQueue(config('meditrack.queue_names.alerts'));
        return $this->ok($alert->load('student'), 'Emergency alert issued.', 201);
    }

    public function show(Request $request, EmergencyAlert $emergencyAlert) { $this->audit->log($request, 'emergency_alert.viewed', $emergencyAlert); return $this->ok($emergencyAlert->load('student')); }

    public function update(Request $request, EmergencyAlert $emergencyAlert)
    {
        RoleGate::nurse($request);
        $before = $emergencyAlert->toArray();
        $data = $request->validate(['severity' => 'sometimes|in:high,critical,emergency', 'title' => 'sometimes|required|string|max:255', 'message' => 'sometimes|required|string', 'status' => 'nullable|string|max:80', 'resolved_at' => 'nullable|date']);
        $emergencyAlert->update($data);
        $this->audit->log($request, 'emergency_alert.updated', $emergencyAlert, $before, $emergencyAlert->toArray());
        return $this->ok($emergencyAlert->fresh('student'));
    }

    public function destroy(Request $request, EmergencyAlert $emergencyAlert)
    {
        RoleGate::nurse($request);
        $emergencyAlert->delete();
        $this->audit->log($request, 'emergency_alert.deleted', $emergencyAlert);
        return $this->ok([], 'Emergency alert deleted.');
    }
}
