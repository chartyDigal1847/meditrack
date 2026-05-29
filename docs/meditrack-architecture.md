# MediTrack Architecture

MediTrack is an independent Laravel 12 service in the DEORIS ecosystem. It owns the `meditrack_service` database and does not join or query other service databases. Integrations use versioned REST APIs, signed events, Redis channels, queues, and portal-issued identity.

## Service Identity

- `service_name`: MediTrack
- `service_key`: `meditrack-service`
- API base: `/api/v1`
- Event channels: `medical.events`, `clinic.notifications`, `clinic.emergency-alerts`
- Queues: `medical`, `notifications`, `alerts`, `events`
- Identity provider: DEORIS Portal only

## Data Ownership

Core owned tables are `students`, `nurses`, `clinic_visits`, `diagnoses`, `medical_records`, `prescriptions`, `health_reports`, `emergency_alerts`, `medical_audit_logs`, `notifications`, `activity_logs`, and `event_outbox`.

Advanced MySQL features are included in `2026_05_24_000001_create_meditrack_core_tables.php`:

- `clinic_visit_analytics` view
- `diagnosis_trends` view
- `sp_student_health_statistics` stored procedure
- `trg_clinic_visit_emergency` trigger for automatic alert creation

## Access Control

- Nurse: create, update, delete clinical data; issue alerts; generate reports.
- Student: view permitted data and submit health concerns.
- Admin: read-only analytics, reports, and audit monitoring.

All clinical updates call `MedicalAuditService` and publish domain events through `MedicalEventService`.

## Events

Published events use HMAC-SHA256 over the nonce and event envelope. Events are stored in `event_outbox` before queued delivery.

Required event names implemented:

- `MedicalApproved`
- `HealthRecordUpdated`
- `EmergencyAlertIssued`
- `ClinicVisitRecorded`
- `DiagnosisUpdated`

Each envelope includes `event_id`, `event_name`, `source_service`, `payload`, `timestamp`, `schema_version`, and `correlation_id`.

## REST API

Main endpoints:

- `GET /api/v1/bootstrap`
- `GET|POST /api/v1/clinic-visits`
- `GET|PUT|DELETE /api/v1/diagnoses/{id}`
- `GET|POST|PUT|DELETE /api/v1/medical-records`
- `GET|POST|PUT|DELETE /api/v1/prescriptions`
- `GET|POST|PUT|DELETE /api/v1/health-reports`
- `GET|POST|PUT|DELETE /api/v1/emergency-alerts`
- `GET /api/v1/visit-history`
- `GET /api/v1/search?q=term`
- `GET /api/search?q=term` for DEORIS federated search with `Authorization: Bearer <MEDITRACK_SEARCH_TOKEN>`

Requests consume portal headers:

- `X-DEORIS-Role`
- `X-DEORIS-User`
- `X-Portal-User-Id`
- `X-Portal-User-Role`
- `X-Portal-User-Email`
- `X-Portal-Token`
- `X-Correlation-ID`

When MediTrack is opened from the DEORIS portal iframe (`?embedded=1`), it loads the centralized bridge from `https://deoris.test/module-bridge.js`. Standalone local development uses the bundled fallback bridge.

## Portal Configuration

The DEORIS portal must include:

- `MEDITRACK_URL=https://meditrack.deoris.test`
- `MEDITRACK_EVENT_SECRET=change-me-meditrack`
- `MEDITRACK_SEARCH_TOKEN=change-me-meditrack-search`

The portal event allow-list must include:

- `MedicalApproved`
- `HealthRecordUpdated`
- `EmergencyAlertIssued`
- `ClinicVisitRecorded`
- `DiagnosisUpdated`

## Deployment

1. Configure `.env` from `.env.example`.
2. Create the MySQL database `meditrack_service`.
3. Run `php artisan migrate --seed`.
4. Use Redis for queues, cache, Pub/Sub, and Reverb.
5. Run queue workers:
   - `php artisan queue:work redis --queue=medical,notifications,alerts,events`
6. Run Laravel scheduler every minute:
   - `php artisan schedule:run`
7. Serve behind HTTPS on a dedicated subdomain.

Supervisor example:

```ini
[program:meditrack-worker]
command=php C:/xampp/htdocs/MediTrack/artisan queue:work redis --queue=medical,notifications,alerts,events --tries=3
autostart=true
autorestart=true
```
