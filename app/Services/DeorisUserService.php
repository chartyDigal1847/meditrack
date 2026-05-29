<?php

namespace App\Services;

use App\Models\Nurse;
use App\Models\Student;

/**
 * DeorisUserService
 *
 * Manages local Student and Nurse records that mirror DEORIS portal identities.
 *
 * SOA COMPLIANCE NOTE:
 * This service does NOT query the DEORIS database directly. Student and Nurse
 * records are populated through two legitimate channels only:
 *   1. SSO login — the portal passes identity via signed session / headers.
 *   2. Inbound events — StudentEnrolled events from the DEORIS event bus.
 *
 * Any attempt to query DB::connection('deoris') here would violate the
 * "never directly access another service database" rule.
 */
class DeorisUserService
{
    /**
     * Sync a student identity received from the DEORIS portal during SSO.
     *
     * @param string $externalId  DEORIS user ID (portal-issued)
     * @param string $email       User email from portal session/headers
     * @param string $name        Full name from portal session/headers
     */
    public function syncStudent(string $externalId, string $email, string $name): ?Student
    {
        $nameParts = explode(' ', trim($name));
        $firstName = array_shift($nameParts) ?: 'Student';
        $lastName  = implode(' ', $nameParts) ?: '';

        // Find by external_id first, then fall back to email match.
        $student = Student::where('external_id', $externalId)->first()
            ?? Student::where('email', $email)->whereNotNull('email')->first();

        if ($student) {
            $student->update([
                'external_id' => $externalId,
                'first_name'  => $firstName,
                'last_name'   => $lastName,
                'email'       => $email ?: $student->email,
                'synced_at'   => now(),
            ]);
            return $student;
        }

        // No local record yet — create a minimal placeholder.
        // student_number will be filled in when a StudentEnrolled event arrives
        // or when the nurse records the first clinic visit with the student number.
        return Student::create([
            'external_id'    => $externalId,
            'student_number' => 'SSO-' . $externalId,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email ?: null,
            'synced_at'      => now(),
        ]);
    }

    /**
     * Sync a nurse identity received from the DEORIS portal during SSO.
     *
     * @param string $externalId  DEORIS user ID (portal-issued)
     * @param string $email       User email from portal session/headers
     * @param string $name        Full name from portal session/headers
     */
    public function syncNurse(string $externalId, string $email, string $name): ?Nurse
    {
        $nurse = Nurse::where('external_id', $externalId)->first()
            ?? Nurse::where('email', $email)->whereNotNull('email')->first();

        if ($nurse) {
            $nurse->update([
                'external_id' => $externalId,
                'name'        => $name ?: $nurse->name,
                'email'       => $email ?: $nurse->email,
                'synced_at'   => now(),
            ]);
            return $nurse;
        }

        return Nurse::create([
            'external_id' => $externalId,
            'name'        => $name ?: 'Clinic Nurse',
            'email'       => $email ?: null,
            'status'      => 'active',
            'synced_at'   => now(),
        ]);
    }

    /**
     * Sync user based on role — delegates to the appropriate method.
     *
     * @param string $role  'student', 'nurse', or 'admin'
     */
    public function syncByRole(string $role, string $externalId, string $email, string $name): Student|Nurse|null
    {
        if ($role === 'nurse' || $role === 'admin') {
            return $this->syncNurse($externalId, $email, $name);
        }

        return $this->syncStudent($externalId, $email, $name);
    }
}
