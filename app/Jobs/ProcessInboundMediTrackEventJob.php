<?php

namespace App\Jobs;

use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * ProcessInboundMediTrackEventJob
 *
 * Processes inbound events from the DEORIS event bus.
 * Handles StudentEnrolled, TuitionPaid, and MedicalApproved events.
 *
 * SOA COMPLIANCE:
 * All student data is sourced exclusively from the event payload published
 * by the originating service. No direct cross-service database queries are
 * performed here. The DEORIS portal is responsible for including all
 * necessary identity fields in the event payload.
 *
 * Retry strategy: exponential backoff — 5, 15, 45, 120, 300 seconds.
 */
class ProcessInboundMediTrackEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    private array $backoff = [5, 15, 45, 120, 300];

    public function __construct(private array $event) {}

    public function handle(): void
    {
        $eventName     = $this->event['name'] ?? $this->event['event_name'] ?? 'Unknown';
        $payload       = $this->event['payload'] ?? $this->event;
        $correlationId = $this->event['correlation_id'] ?? null;

        Log::info('[MediTrack] Processing inbound event', [
            'event_name'     => $eventName,
            'correlation_id' => $correlationId,
        ]);

        match ($eventName) {
            'StudentEnrolled'  => $this->handleStudentEnrolled($payload, $correlationId),
            'TuitionPaid'      => $this->handleTuitionPaid($payload, $correlationId),
            'MedicalApproved'  => $this->handleMedicalApproved($payload, $correlationId),
            default            => Log::info('[MediTrack] Unhandled event type — skipping', ['event' => $eventName]),
        };

        Log::info('[MediTrack] Event processed successfully', ['event_name' => $eventName]);
    }

    /**
     * Sync student record from data carried in the StudentEnrolled event payload.
     * The originating service (EnrollEase / DEORIS Portal) must include all
     * required identity fields in the payload — no DB cross-queries needed.
     */
    private function handleStudentEnrolled(array $payload, ?string $correlationId): void
    {
        $externalId    = (string) ($payload['student_id'] ?? $payload['external_id'] ?? '');
        $studentNumber = (string) ($payload['student_number'] ?? '');
        $email         = (string) ($payload['email'] ?? '');
        $firstName     = (string) ($payload['first_name'] ?? '');
        $lastName      = (string) ($payload['last_name'] ?? '');
        $gradeLevel    = (string) ($payload['grade_level'] ?? '');
        $section       = (string) ($payload['section'] ?? '');

        // Derive first/last from a combined name field if individual parts absent
        if ($firstName === '' && isset($payload['name'])) {
            $parts     = explode(' ', trim((string) $payload['name']));
            $firstName = array_shift($parts) ?: 'Student';
            $lastName  = implode(' ', $parts);
        }

        if ($externalId === '' && $studentNumber === '' && $email === '') {
            Log::warning('[MediTrack] StudentEnrolled payload missing identity fields', [
                'payload'        => $payload,
                'correlation_id' => $correlationId,
            ]);
            return;
        }

        // Build lookup conditions — match on any known identifier
        $student = null;
        if ($externalId !== '') {
            $student = Student::where('external_id', $externalId)->first();
        }
        if (! $student && $studentNumber !== '') {
            $student = Student::where('student_number', $studentNumber)->first();
        }
        if (! $student && $email !== '') {
            $student = Student::where('email', $email)->first();
        }

        $updateData = array_filter([
            'external_id'    => $externalId ?: null,
            'student_number' => $studentNumber ?: null,
            'first_name'     => $firstName ?: null,
            'last_name'      => $lastName ?: null,
            'email'          => $email ?: null,
            'grade_level'    => $gradeLevel ?: null,
            'section'        => $section ?: null,
            'synced_at'      => now(),
        ], fn ($v) => $v !== null);

        if ($student) {
            $student->update($updateData);
        } else {
            // Only create if we have enough data to form a valid record
            if ($studentNumber === '' && $externalId === '') {
                Log::warning('[MediTrack] Cannot create student — insufficient payload data', [
                    'correlation_id' => $correlationId,
                ]);
                return;
            }

            Student::create(array_merge([
                'student_number' => $studentNumber ?: 'STU-' . $externalId,
                'first_name'     => $firstName ?: 'Student',
                'last_name'      => $lastName ?: '',
            ], $updateData));
        }

        Log::info('[MediTrack] StudentEnrolled synced', [
            'external_id'    => $externalId,
            'student_number' => $studentNumber,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * Update tuition-paid flag on the student's medical_flags JSON column.
     */
    private function handleTuitionPaid(array $payload, ?string $correlationId): void
    {
        $student = $this->findStudentFromPayload($payload);
        if (! $student) {
            Log::info('[MediTrack] TuitionPaid — student not found locally, skipping', [
                'correlation_id' => $correlationId,
            ]);
            return;
        }

        $flags                = $student->medical_flags ?? [];
        $flags['tuition_paid'] = true;
        $student->update(['medical_flags' => $flags, 'synced_at' => now()]);

        Log::info('[MediTrack] TuitionPaid flag updated', [
            'student_id'     => $student->id,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * Update medical-approved flag on the student's medical_flags JSON column.
     */
    private function handleMedicalApproved(array $payload, ?string $correlationId): void
    {
        $student = $this->findStudentFromPayload($payload);
        if (! $student) {
            Log::info('[MediTrack] MedicalApproved — student not found locally, skipping', [
                'correlation_id' => $correlationId,
            ]);
            return;
        }

        $flags                          = $student->medical_flags ?? [];
        $flags['medical_approved']      = true;
        $flags['medical_approved_at']   = now()->toIso8601String();
        $student->update(['medical_flags' => $flags, 'synced_at' => now()]);

        Log::info('[MediTrack] MedicalApproved flag updated', [
            'student_id'     => $student->id,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * Resolve a local Student from payload identifiers without any cross-DB query.
     */
    private function findStudentFromPayload(array $payload): ?Student
    {
        $externalId    = (string) ($payload['student_id'] ?? $payload['external_id'] ?? '');
        $studentNumber = (string) ($payload['student_number'] ?? '');
        $email         = (string) ($payload['email'] ?? '');

        if ($externalId !== '') {
            $student = Student::where('external_id', $externalId)->first();
            if ($student) return $student;
        }
        if ($studentNumber !== '') {
            $student = Student::where('student_number', $studentNumber)->first();
            if ($student) return $student;
        }
        if ($email !== '') {
            return Student::where('email', $email)->first();
        }

        return null;
    }

    public function backoff(): array
    {
        return $this->backoff;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[MediTrack] Event processing permanently failed', [
            'error' => $exception->getMessage(),
            'event' => $this->event,
        ]);
    }
}
