# MediTrack

**MediTrack** is the school health records and clinic management module of the **DEORIS** ecosystem. It is a fully independent SOA-based Laravel 12 service that owns its own database and communicates with other services exclusively through REST APIs and signed events.

---

## Overview

MediTrack manages:

- Clinic visits and diagnoses
- Student medical records and prescriptions
- Health reports and analytics
- Emergency health alerts with real-time broadcasting
- Student health concerns
- Medical audit trails
- Event-driven integration with the DEORIS ecosystem

---

## Service Identity

| Key | Value |
|---|---|
| `service_name` | MediTrack |
| `service_key` | `meditrack-service` |
| API base | `/api/v1` |
| Redis channels | `medical.events`, `clinic.notifications`, `clinic.emergency-alerts` |
| Queues | `medical`, `notifications`, `alerts`, `events` |
| Identity provider | DEORIS Portal (SSO only) |

---

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL 8+
- Redis
- Composer
- Node.js + npm (for asset compilation)
- Laravel Reverb (WebSocket server)

---

## Installation

### 1. Clone and install dependencies

```bash
cd C:/xampp/htdocs/MediTrack
composer install
npm install && npm run build
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at minimum:

```dotenv
APP_URL=https://meditrack.deoris.test

DB_DATABASE=meditrack_service
DB_USERNAME=root
DB_PASSWORD=

APP_PORTAL_URL=https://deoris.test
DEORIS_PORTAL_URL=https://meditrack.deoris.test

MEDITRACK_EVENT_SECRET=change-me-meditrack
MEDITRACK_SEARCH_TOKEN=change-me-meditrack-search

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
```

### 3. Create the database

```sql
CREATE DATABASE meditrack_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations and seed

```bash
php artisan migrate --seed
```

This creates all tables, views, stored procedures, and the trigger, then seeds demo data.

### 5. Start queue workers

```bash
php artisan queue:work redis --queue=medical,notifications,alerts,events --tries=3
```

### 6. Start the WebSocket server (Reverb)

```bash
php artisan reverb:start
```

### 7. Start the scheduler (every minute via cron or manually)

```bash
php artisan schedule:run
```

---

## Supervisor Configuration

For production, manage the queue worker with Supervisor:

```ini
[program:meditrack-worker]
command=php C:/xampp/htdocs/MediTrack/artisan queue:work redis --queue=medical,notifications,alerts,events --tries=3
autostart=true
autorestart=true
stderr_logfile=/var/log/meditrack-worker.err.log
stdout_logfile=/var/log/meditrack-worker.out.log
```

---

## REST API

All endpoints require a valid DEORIS portal session or signed portal headers.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/bootstrap` | Dashboard data for the authenticated role |
| GET | `/api/v1/analytics` | Aggregate clinic analytics |
| GET/POST | `/api/v1/clinic-visits` | List or record clinic visits |
| GET/PUT/DELETE | `/api/v1/clinic-visits/{id}` | Manage a specific visit |
| GET | `/api/v1/visit-history` | Alias for clinic visits list |
| GET/POST | `/api/v1/diagnoses` | List or create diagnoses |
| GET/PUT/DELETE | `/api/v1/diagnoses/{id}` | Manage a specific diagnosis |
| GET/POST | `/api/v1/medical-records` | List or create medical records |
| GET/PUT/DELETE | `/api/v1/medical-records/{id}` | Manage a specific record |
| GET/POST | `/api/v1/prescriptions` | List or create prescriptions |
| GET/PUT/DELETE | `/api/v1/prescriptions/{id}` | Manage a specific prescription |
| GET/POST | `/api/v1/health-reports` | List or generate health reports |
| GET/PUT/DELETE | `/api/v1/health-reports/{id}` | Manage a specific report |
| GET/POST | `/api/v1/emergency-alerts` | List or issue emergency alerts |
| GET/PUT/DELETE | `/api/v1/emergency-alerts/{id}` | Manage a specific alert |
| GET/PUT/DELETE | `/api/v1/notifications` | Notification management |
| GET/POST | `/api/v1/student-concerns` | Student health concern submissions |
| GET | `/api/v1/search?q=term` | Authenticated search |
| GET | `/api/search?q=term` | Federated search (bearer token) |

---

## Access Control

| Role | Permissions |
|---|---|
| **Nurse** | Full clinical management — create, update, delete all medical data; issue alerts; generate reports |
| **Student** | View own records, clinic history, prescriptions; submit health concerns |
| **Admin** | Read-only — analytics, reports, audit monitoring |

All write operations are guarded by `RoleGate::nurse()` and every action is logged to `medical_audit_logs`.

---

## Events Published

MediTrack publishes the following signed events to the DEORIS Event Hub:

| Event | Trigger |
|---|---|
| `ClinicVisitRecorded` | New clinic visit created |
| `DiagnosisUpdated` | Diagnosis created or updated |
| `MedicalApproved` | Medical record created |
| `HealthRecordUpdated` | Medical record or clinic visit updated |
| `EmergencyAlertIssued` | Emergency alert created |

Each event envelope includes `event_id`, `event_name`, `source_service`, `payload`, `timestamp`, `schema_version`, and `correlation_id`. Events are signed with HMAC-SHA256 and stored in `event_outbox` before queued delivery.

## Events Consumed

MediTrack listens for these inbound events from the DEORIS event bus:

| Event | Action |
|---|---|
| `StudentEnrolled` | Creates or updates local student record from payload |
| `TuitionPaid` | Sets `tuition_paid` flag on student |
| `MedicalApproved` | Sets `medical_approved` flag on student |

---

## SOA Compliance

MediTrack strictly follows SOA principles:

- Owns only the `meditrack_service` database
- Never queries another service's database directly
- All student/nurse identity data comes from portal SSO sessions or inbound events
- Communicates with DEORIS exclusively through signed REST API calls and events

---

## Database

Core tables: `students`, `nurses`, `clinic_visits`, `diagnoses`, `medical_records`, `prescriptions`, `health_reports`, `emergency_alerts`, `medical_audit_logs`, `notifications`, `event_outbox`, `student_concerns`, `deoris_event_inbox`, `activity_logs`

Advanced MySQL features (created in migration):
- Views: `clinic_visit_analytics`, `diagnosis_trends`
- Stored procedure: `sp_student_health_statistics`
- Trigger: `trg_clinic_visit_emergency` (auto-creates alert on emergency severity insert)

---

## Architecture Documentation

See [`docs/meditrack-architecture.md`](docs/meditrack-architecture.md) for the full architecture reference including Redis channel configuration, queue worker documentation, and DEORIS portal setup requirements.

---

## License

Proprietary — DEORIS Ecosystem.
