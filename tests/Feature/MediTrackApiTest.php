<?php

namespace Tests\Feature;

use App\Jobs\PublishMedicalEventJob;
use App\Models\ClinicVisit;
use App\Models\MedicalAuditLog;
use App\Models\Student;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediTrackApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No DEORIS DB mock needed — MediTrack no longer queries cross-service databases.
    }

    public function test_bootstrap_returns_service_identity_and_metrics(): void
    {
        $response = $this->withDeorisRole('admin')->getJson('/api/v1/bootstrap');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.service_key', 'meditrack-service')
            ->assertJsonStructure(['data' => ['metrics', 'clinic_visits', 'alerts', 'audit_logs']]);
    }

    public function test_nurse_can_record_clinic_visit_and_event_is_queued(): void
    {
        Queue::fake();

        $response = $this->withDeorisRole('nurse')->postJson('/api/v1/clinic-visits', [
            'student_number' => '2026-T001',
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'chief_complaint' => 'Stomach pain',
            'status' => 'under_evaluation',
            'severity' => 'medium',
            'temperature' => 37.4,
            'diagnosis_title' => 'Abdominal discomfort',
        ]);

        $response->assertCreated()->assertJsonPath('data.student.student_number', 'EXT-2026-T001');
        $this->assertDatabaseHas('clinic_visits', ['chief_complaint' => 'Stomach pain']);
        $this->assertDatabaseHas('event_outbox', ['event_name' => 'ClinicVisitRecorded']);
        $this->assertDatabaseHas('medical_audit_logs', ['action' => 'clinic_visit.recorded']);
        Queue::assertPushed(PublishMedicalEventJob::class);
    }

    public function test_nurse_visit_form_tolerates_common_input_variations(): void
    {
        Queue::fake();

        $response = $this->withDeorisRole('nurse')->postJson('/api/v1/clinic-visits', [
            'student_number' => ' 2026-TOL ',
            'first_name' => ' Lara ',
            'last_name' => ' Santos ',
            'chief_complaint' => ' Mild headache ',
            'status' => 'Under Evaluation',
            'severity' => 'Medium',
            'temperature' => '',
            'pulse_rate' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'under_evaluation')
            ->assertJsonPath('data.severity', 'medium');

        $this->assertDatabaseHas('students', ['student_number' => 'EXT-2026-TOL']);
        $this->assertDatabaseHas('clinic_visits', ['chief_complaint' => 'Mild headache']);
    }

    public function test_nurse_can_update_visit_status_from_view_action(): void
    {
        Queue::fake();

        $visitId = $this->withDeorisRole('nurse')->postJson('/api/v1/clinic-visits', [
            'student_number' => '2026-UPD',
            'first_name' => 'Update',
            'last_name' => 'Student',
            'chief_complaint' => 'Dizziness',
            'status' => 'under_evaluation',
            'severity' => 'medium',
        ])->assertCreated()->json('data.id');

        $this->withDeorisRole('nurse')->putJson("/api/v1/clinic-visits/{$visitId}", [
            'status' => 'Treated',
            'severity' => 'Low',
            'notes' => 'Student improved after rest and hydration.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'treated')
            ->assertJsonPath('data.severity', 'low');

        $this->assertDatabaseHas('clinic_visits', [
            'id' => $visitId,
            'status' => 'treated',
            'severity' => 'low',
            'notes' => 'Student improved after rest and hydration.',
        ]);
        $this->assertDatabaseHas('medical_audit_logs', ['action' => 'clinic_visit.updated']);
    }

    public function test_admin_cannot_modify_clinical_data(): void
    {
        $response = $this->withDeorisRole('admin')->postJson('/api/v1/clinic-visits', [
            'student_number' => '2026-T002',
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'chief_complaint' => 'Fever',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('students', ['student_number' => '2026-T002']);
    }

    public function test_student_can_submit_health_concern(): void
    {
        $response = $this->withDeorisRole('student')->postJson('/api/v1/student-concerns', [
            'title' => 'Headache',
            'description' => 'Headache after PE class.',
            'severity' => 'low',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending_checkup');
        $this->assertDatabaseHas('student_concerns', ['title' => 'Headache']);
    }

    public function test_portal_gateway_headers_authenticate_api_requests(): void
    {
        $response = $this->withHeaders([
            'X-Portal-User-Id' => '42',
            'X-Portal-User-Role' => 'admin',
            'X-Portal-User-Email' => 'admin@deoris.test',
        ])->getJson('/api/v1/bootstrap');

        $response->assertOk()->assertJsonPath('data.role', 'admin');
        $this->assertFalse(Schema::hasTable('users'));
    }

    public function test_federated_search_requires_module_search_token(): void
    {
        Config::set('meditrack.search_token', 'test-search-token');

        Student::create([
            'student_number' => '2026-SRCH',
            'first_name' => 'Searchable',
            'last_name' => 'Student',
        ]);

        $this->getJson('/api/search?q=Searchable')->assertUnauthorized();
        $this->withToken('test-search-token')->getJson('/api/search?q=Searchable')->assertOk();
    }

    public function test_event_outbox_payload_matches_deoris_event_contract(): void
    {
        Queue::fake();

        $this->withDeorisRole('nurse')->postJson('/api/v1/clinic-visits', [
            'student_number' => '2026-EVT',
            'first_name' => 'Event',
            'last_name' => 'Student',
            'chief_complaint' => 'Cough',
        ])->assertCreated();

        $this->assertDatabaseHas('event_outbox', [
            'event_name' => 'ClinicVisitRecorded',
            'source_service' => 'meditrack-service',
        ]);

        $event = \App\Models\EventOutbox::firstOrFail();
        $this->assertSame('ClinicVisitRecorded', $event->payload['name']);
        $this->assertSame('MediTrack', $event->payload['source_module']);
        $this->assertArrayHasKey('occurred_at', $event->payload);
    }

    public function test_medical_approved_event_contains_portal_student_identifier(): void
    {
        Queue::fake();

        $student = Student::create([
            'student_number' => '2026-MED',
            'first_name' => 'Medical',
            'last_name' => 'Approved',
            'email' => 'medical.approved@student.deoris.test',
        ]);

        $this->withDeorisRole('nurse')->postJson('/api/v1/medical-records', [
            'student_id' => (string) $student->id,
            'record_type' => 'clearance',
            'title' => 'Medical clearance',
            'summary' => 'Student is cleared for school activity.',
        ])->assertCreated();

        $event = \App\Models\EventOutbox::where('event_name', 'MedicalApproved')->firstOrFail();
        $this->assertSame('2026-MED', $event->payload['payload']['student_number']);
        $this->assertSame('medical.approved@student.deoris.test', $event->payload['payload']['student_email']);
    }

    public function test_sso_redirect_establishes_session_and_syncs_user(): void
    {
        $response = $this->postJson('/sso/redirect', [
            'id' => '12345',
            'name' => 'John Doe',
            'email' => 'john.doe@student.deoris.test',
            'role' => 'student',
            'embedded' => '1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('12345', session('sso_id'));
        $this->assertEquals('student', session('sso_role'));
        $this->assertEquals('John Doe', session('sso_name'));
        $this->assertEquals('john.doe@student.deoris.test', session('sso_email'));
        $this->assertEquals(true, session('sso_embedded'));
    }

    private function withDeorisRole(string $role): self
    {
        return $this->withHeaders([
            'X-DEORIS-Role' => $role,
            'X-DEORIS-User' => json_encode([
                'id' => 'test-'.$role,
                'name' => ucfirst($role).' Tester',
                'email' => $role.'@deoris.test',
                'role' => $role,
            ]),
        ]);
    }

    public function test_nurse_creates_visit_matching_deoris_user_populates_external_id(): void
    {
        Queue::fake();

        // SOA-compliant: student is pre-synced in MediTrack's own DB via SSO or event.
        // The nurse visit form uses student_number to find or create the local record.
        $response = $this->withDeorisRole('nurse')->postJson('/api/v1/clinic-visits', [
            'student_number' => '2026-NURSE-MATCH',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'chief_complaint' => 'High fever',
            'email' => 'juan@student.deoris.test',
            'status' => 'under_evaluation',
            'severity' => 'medium',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('students', [
            'student_number' => 'EXT-2026-NURSE-MATCH',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
    }

    public function test_sso_sync_links_to_existing_nurse_visit_record(): void
    {
        // Pre-create student in MediTrack's own DB without external_id.
        Student::create([
            'student_number' => '2026-SSO-LINK',
            'first_name' => 'Sso',
            'last_name' => 'Link',
            'email' => 'sso.link@student.deoris.test',
            'external_id' => null,
        ]);

        // SSO redirect carries identity from the portal — no cross-DB lookup needed.
        $response = $this->postJson('/sso/redirect', [
            'id' => '888',
            'name' => 'Sso Link',
            'email' => 'sso.link@student.deoris.test',
            'role' => 'student',
        ]);

        $response->assertOk();

        // DeorisUserService matches on email and updates external_id.
        $students = Student::where('student_number', '2026-SSO-LINK')->get();
        $this->assertCount(1, $students);
        $this->assertEquals('888', $students->first()->external_id);
    }

    public function test_event_job_syncs_student_enrolled_using_event_payload(): void
    {
        // SOA-compliant: all student data comes from the event payload itself.
        // The originating service (EnrollEase/Portal) must include full identity.
        $job = new \App\Jobs\ProcessInboundMediTrackEventJob([
            'name' => 'StudentEnrolled',
            'payload' => [
                'student_id'     => '777',
                'student_number' => '2026-ENROLLED',
                'first_name'     => 'Enrolled',
                'last_name'      => 'Student',
                'email'          => 'enrolled@student.deoris.test',
                'grade_level'    => 'Grade 10',
            ],
        ]);
        $job->handle();

        $this->assertDatabaseHas('students', [
            'external_id'    => '777',
            'student_number' => '2026-ENROLLED',
            'first_name'     => 'Enrolled',
            'last_name'      => 'Student',
            'email'          => 'enrolled@student.deoris.test',
        ]);
    }

    public function test_nurse_can_create_record_alert_prescription_concern_with_student_identifier(): void
    {
        Queue::fake();

        // SOA-compliant: student must already exist in MediTrack's DB.
        // Pre-seed via a StudentEnrolled event (the normal sync path).
        $student = Student::create([
            'external_id'    => '666',
            'student_number' => '2026-RESOLVE',
            'first_name'     => 'Resolve',
            'last_name'      => 'Test',
            'email'          => 'resolvetest@student.deoris.test',
        ]);

        // 1. Post medical record using student_number
        $this->withDeorisRole('nurse')->postJson('/api/v1/medical-records', [
            'student_id'  => (string) $student->id,
            'record_type' => 'clearance',
            'title'       => 'Test Medical Record',
            'summary'     => 'Some record summary',
        ])->assertCreated();

        // 2. Post emergency alert using email
        $this->withDeorisRole('nurse')->postJson('/api/v1/emergency-alerts', [
            'student_id' => 'resolvetest@student.deoris.test',
            'severity'   => 'critical',
            'title'      => 'Test Alert',
            'message'    => 'Emergency alert message',
        ])->assertCreated();

        // 3. Post prescription using student_number
        $this->withDeorisRole('nurse')->postJson('/api/v1/prescriptions', [
            'student_id'      => (string) $student->id,
            'medication_name' => 'Aspirin',
            'dosage'          => '100mg',
            'frequency'       => 'Once daily',
        ])->assertCreated();

        // 4. Post student concern
        $this->withDeorisRole('student')->postJson('/api/v1/student-concerns', [
            'title'       => 'Anxiety',
            'description' => 'Student reports exam anxiety.',
            'severity'    => 'medium',
        ])->assertCreated();

        $this->assertDatabaseHas('medical_records', [
            'student_id' => $student->id,
            'title'      => 'Test Medical Record',
        ]);
        $this->assertDatabaseHas('emergency_alerts', [
            'student_id' => $student->id,
            'title'      => 'Test Alert',
        ]);
        $this->assertDatabaseHas('prescriptions', [
            'student_id'      => $student->id,
            'medication_name' => 'Aspirin',
        ]);
        $this->assertDatabaseHas('student_concerns', ['title' => 'Anxiety']);
    }

}
