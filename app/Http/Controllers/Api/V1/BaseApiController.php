<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Nurse;
use App\Services\MedicalAuditService;
use App\Services\MedicalEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    public function __construct(
        protected MedicalAuditService $audit,
        protected MedicalEventService $events,
    ) {}

    protected function ok(mixed $data = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function role(Request $request): string
    {
        return (string) $request->attributes->get('meditrack_role', 'student');
    }

    protected function nurseFor(Request $request): Nurse
    {
        $user = $request->user();

        return Nurse::firstOrCreate(
            ['external_id' => $user?->external_id ?: 'system-nurse'],
            ['name' => $user?->name ?: 'Clinic Nurse', 'email' => $user?->email, 'status' => 'active']
        );
    }

    protected function resolveStudent(?string $identifier): ?\App\Models\Student
    {
        if ($identifier === null) {
            return null;
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // Look up only in MediTrack's own database — no cross-service DB access.
        // Students are synced here via SSO login or inbound events (StudentEnrolled).
        return \App\Models\Student::where('id', $identifier)
            ->orWhere('student_number', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('external_id', $identifier)
            ->first();
        // Returns null if not found — callers must handle this and return 422.
    }
}
