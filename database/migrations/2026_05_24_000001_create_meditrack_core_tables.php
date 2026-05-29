<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('student_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('grade_level')->nullable()->index();
            $table->string('section')->nullable()->index();
            $table->date('birthdate')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_contact')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->json('medical_flags')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nurses', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('license_number')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clinic_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visit_code')->unique();
            $table->string('chief_complaint');
            $table->string('visit_type')->default('walk_in')->index();
            $table->string('status')->default('pending_checkup')->index();
            $table->string('severity')->default('low')->index();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable()->index();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'status']);
            $table->index(['nurse_id', 'checked_in_at']);
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable()->index();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->string('status')->default('diagnosed')->index();
            $table->timestamp('diagnosed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('record_type')->index();
            $table->string('title');
            $table->text('summary');
            $table->text('sensitive_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'record_type']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medication_name')->index();
            $table->string('dosage');
            $table->string('frequency');
            $table->string('duration')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status')->default('issued')->index();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('health_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type')->index();
            $table->string('title');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->longText('summary');
            $table->json('metrics')->nullable();
            $table->string('status')->default('generated')->index();
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinic_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alert_code')->unique();
            $table->string('severity')->default('high')->index();
            $table->string('title');
            $table->text('message');
            $table->string('status')->default('active')->index();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medical_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_external_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->index();
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->string('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_external_id')->nullable()->index();
            $table->string('recipient_role')->index();
            $table->string('type')->index();
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name')->index();
            $table->string('source_service')->index();
            $table->json('payload');
            $table->string('signature');
            $table->string('nonce')->unique();
            $table->string('schema_version')->default('1.0');
            $table->uuid('correlation_id')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('student_concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_student_id')->nullable()->index();
            $table->string('title');
            $table->text('description');
            $table->string('severity')->default('low')->index();
            $table->string('status')->default('pending_checkup')->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE OR REPLACE VIEW clinic_visit_analytics AS SELECT DATE(checked_in_at) AS visit_date, status, severity, COUNT(*) AS total_visits, AVG(temperature) AS avg_temperature FROM clinic_visits WHERE deleted_at IS NULL GROUP BY DATE(checked_in_at), status, severity');
            DB::statement('CREATE OR REPLACE VIEW diagnosis_trends AS SELECT title, code, COUNT(*) AS diagnosis_count, DATE_FORMAT(diagnosed_at, "%Y-%m") AS diagnosis_month FROM diagnoses WHERE deleted_at IS NULL GROUP BY title, code, DATE_FORMAT(diagnosed_at, "%Y-%m")');
            DB::statement('CREATE PROCEDURE sp_student_health_statistics(IN p_student_id BIGINT) SELECT s.id, s.student_number, COUNT(DISTINCT cv.id) AS visit_count, COUNT(DISTINCT d.id) AS diagnosis_count, COUNT(DISTINCT ea.id) AS emergency_count FROM students s LEFT JOIN clinic_visits cv ON cv.student_id = s.id LEFT JOIN diagnoses d ON d.student_id = s.id LEFT JOIN emergency_alerts ea ON ea.student_id = s.id WHERE s.id = p_student_id GROUP BY s.id, s.student_number');
            DB::statement('CREATE TRIGGER trg_clinic_visit_emergency AFTER INSERT ON clinic_visits FOR EACH ROW BEGIN IF NEW.severity = "emergency" OR NEW.status = "emergency" THEN INSERT INTO emergency_alerts (student_id, clinic_visit_id, nurse_id, alert_code, severity, title, message, status, issued_at, created_at, updated_at) VALUES (NEW.student_id, NEW.id, NEW.nurse_id, UUID(), "critical", "Automatic emergency clinic alert", CONCAT("Emergency visit recorded: ", NEW.chief_complaint), "active", NOW(), NOW(), NOW()); END IF; END');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_clinic_visit_emergency');
            DB::statement('DROP PROCEDURE IF EXISTS sp_student_health_statistics');
            DB::statement('DROP VIEW IF EXISTS diagnosis_trends');
            DB::statement('DROP VIEW IF EXISTS clinic_visit_analytics');
        }

        foreach (['student_concerns', 'event_outbox', 'notifications', 'medical_audit_logs', 'emergency_alerts', 'health_reports', 'prescriptions', 'medical_records', 'diagnoses', 'clinic_visits', 'nurses', 'students'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
