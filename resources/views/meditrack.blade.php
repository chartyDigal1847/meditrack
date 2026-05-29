<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MediTrack | DEORIS</title>
    <script>
        if (window.self !== window.top) document.documentElement.classList.add('is-framed');
        window.MEDITRACK = {
            apiBase: "{{ url('/api/v1') }}",
            portalOrigin: "{{ $service['trusted_portal_url'] }}",
            serviceName: "{{ $service['service_name'] }}",
            serviceKey: "{{ $service['service_key'] }}",
            embedded: {{ request()->boolean('embedded') ? 'true' : 'false' }},
        };
        window.DEORIS_SSO_MODE = "module";
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/meditrack.css') }}?v={{ filemtime(public_path('css/meditrack.css')) }}">
</head>
<body>
    <div class="app-shell">
        <header class="app-header" id="appHeader">
            <div class="app-header-inner">
                <a href="{{ route('meditrack.dashboard') }}" class="app-brand" aria-label="MediTrack home">
                    <div class="app-brand-badge">M</div>
                    <div>
                        <div class="app-brand-name">MediTrack</div>
                        <small class="app-brand-sub">School Health Records</small>
                    </div>
                </a>
                <div class="app-header-spacer"></div>
                <div class="app-user-chip">
                    <div class="app-user-avatar" id="userInitial">U</div>
                    <span id="userName">Portal User</span>
                    <span class="app-role-badge" id="userRole">student</span>
                </div>
            </div>
        </header>

        <main class="app-main">
            <section class="app-content">
                <div id="loader" class="loader-panel">
                    <div class="loader-ring"></div>
                    <strong>Loading MediTrack</strong>
                    <span id="loaderText">Validating portal session and clinic data.</span>
                </div>

                <div id="workspace" class="workspace hidden">
                    <div class="module-topbar">
                        <div>
                            <h1 class="module-title">Dashboard</h1>
                            <p class="module-sub">Monitoring overview — {{ now()->format('l, F j, Y') }}</p>
                        </div>
                        <div class="module-actions" aria-label="MediTrack navigation">
                            <button class="top-action active" data-page="dashboard"><i class="fa-solid fa-chart-pie"></i> Dashboard</button>
                            <button class="top-action student-hidden" data-page="visits"><i class="fa-solid fa-stethoscope"></i> Visits</button>
                            <button class="top-action" data-page="records"><i class="fa-solid fa-file-medical"></i> Records</button>
                            <button class="top-action student-hidden" data-page="reports"><i class="fa-solid fa-chart-simple"></i> Reports</button>
                            <button class="top-action student-hidden" data-page="alerts"><i class="fa-solid fa-triangle-exclamation"></i> Alerts</button>
                            <button class="top-action" data-page="forms"><i class="fa-solid fa-clipboard-list"></i> Forms</button>
                            <button class="top-action" data-page="student"><i class="fa-solid fa-user-graduate"></i> Student</button>
                            <button class="top-action student-hidden" data-page="manage"><i class="fa-solid fa-cogs"></i> Manage</button>
                            <button class="top-action top-action-primary nurse-only" data-open-visit><i class="fa-solid fa-plus"></i> New Visit</button>
                        </div>
                    </div>

                    <section class="page active" id="page-dashboard">
                        <div class="privacy-notice">
                            <i class="fa-solid fa-lock"></i>
                            <span id="roleNotice">Medical data access is restricted by portal role and every view or update is audited.</span>
                        </div>
                        <div class="metrics-grid" id="metricsGrid"></div>
                        <div class="dashboard-grid">
                            <article class="card student-hidden">
                                <div class="card-header"><h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Clinic Visits</h2></div>
                                <div class="card-body dense-list" id="recentVisits"></div>
                            </article>
                            <article class="card student-hidden">
                                <div class="card-header"><h2><i class="fa-solid fa-triangle-exclamation"></i> Emergency Alerts</h2></div>
                                <div class="card-body dense-list" id="alertList"></div>
                            </article>
                            <!-- Student Reports dashboard widgets -->
                            <article class="card student-only hidden">
                                <div class="card-header"><h2><i class="fa-solid fa-chart-simple"></i> Diagnosis Trends</h2></div>
                                <div class="card-body bars" id="trendBarsDashboard"></div>
                            </article>
                            <article class="card student-only hidden">
                                <div class="card-header"><h2><i class="fa-solid fa-file-invoice"></i> Generated Reports</h2></div>
                                <div class="card-body dense-list" id="reportListDashboard"></div>
                            </article>
                        </div>
                    </section>

                    <section class="page" id="page-visits">
                        <div class="page-tools">
                            <button class="btn btn-secondary" data-refresh><i class="fa-solid fa-rotate"></i> Refresh</button>
                        </div>
                        <div class="toolbar">
                            <input class="field" id="visitSearch" placeholder="Search student, complaint, diagnosis">
                            <select class="field field-select" id="visitStatus">
                                <option value="">All statuses</option>
                                <option value="pending_checkup">Pending checkup</option>
                                <option value="under_evaluation">Under evaluation</option>
                                <option value="diagnosed">Diagnosed</option>
                                <option value="treated">Treated</option>
                                <option value="referred">Referred</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="table-card"><table><thead><tr><th>Student</th><th>Complaint</th><th>Status</th><th>Severity</th><th>Nurse</th><th>Date</th><th></th></tr></thead><tbody id="visitRows"></tbody></table></div>
                    </section>

                    <section class="page" id="page-records">
                        <div class="page-tools">
                            <button class="btn btn-primary nurse-only" data-open-record><i class="fa-solid fa-file-circle-plus"></i> Add Record</button>
                            <button class="btn btn-primary nurse-only" data-open-prescription><i class="fa-solid fa-prescription-bottle-medical"></i> Add Prescription</button>
                        </div>
                        <div class="dashboard-grid three">
                            <article class="card"><div class="card-header"><h2>Records</h2></div><div class="card-body dense-list" id="recordList"></div></article>
                            <article class="card"><div class="card-header"><h2>Diagnoses</h2></div><div class="card-body dense-list" id="diagnosisList"></div></article>
                            <article class="card"><div class="card-header"><h2>Prescriptions</h2></div><div class="card-body dense-list" id="prescriptionList"></div></article>
                        </div>
                    </section>

                    <section class="page" id="page-reports">
                        <div class="page-tools"><button class="btn btn-primary nurse-only" data-open-report><i class="fa-solid fa-chart-simple"></i> Generate Report</button></div>
                        <div class="dashboard-grid">
                            <article class="card"><div class="card-header"><h2>Diagnosis Trends</h2></div><div class="card-body bars" id="trendBars"></div></article>
                            <article class="card"><div class="card-header"><h2>Generated Reports</h2></div><div class="card-body dense-list" id="reportList"></div></article>
                        </div>
                    </section>

                    <section class="page" id="page-alerts">
                        <div class="page-tools"><button class="btn btn-danger nurse-only" data-open-alert><i class="fa-solid fa-bolt"></i> Issue Alert</button></div>
                        <div class="alert-board" id="alertBoard"></div>
                    </section>

                    <section class="page" id="page-forms">
                        <div class="form-grid">
                            <button class="form-tile nurse-only" data-open-visit><i class="fa-solid fa-stethoscope"></i><span>Clinic Visit</span></button>
                            <button class="form-tile nurse-only" data-open-record><i class="fa-solid fa-file-medical"></i><span>Medical Record</span></button>
                            <button class="form-tile nurse-only" data-open-prescription><i class="fa-solid fa-prescription-bottle-medical"></i><span>Prescription</span></button>
                            <button class="form-tile nurse-only" data-open-alert><i class="fa-solid fa-triangle-exclamation"></i><span>Emergency Alert</span></button>
                            <button class="form-tile" data-open-concern><i class="fa-solid fa-message-medical"></i><span>Health Concern</span></button>
                        </div>
                    </section>

                    <section class="page" id="page-student">
                        <div class="page-tools"><button class="btn btn-primary" data-open-concern><i class="fa-solid fa-message-medical"></i> Report Concern</button></div>
                        <div class="card"><div class="card-header"><h2>Student Health Concerns</h2></div><div class="card-body dense-list" id="concernList"></div></div>
                    </section>

                    <section class="page" id="page-manage">
                        <div class="dashboard-grid three">
                            <article class="card"><div class="card-header"><h2>Nurse Activity</h2></div><div class="card-body dense-list" id="activityList"></div></article>
                            <article class="card"><div class="card-header"><h2>Notifications</h2></div><div class="card-body dense-list" id="notificationList"></div></article>
                            <article class="card"><div class="card-header"><h2>Audit</h2></div><div class="card-body table-card"><table><thead><tr><th>Actor</th><th>Role</th><th>Action</th><th>Target</th><th>When</th></tr></thead><tbody id="auditRows"></tbody></table></div></article>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </div>

    <div id="modalHost"></div>
    <div id="toastContainer"></div>

    <script src="{{ rtrim(config('app.portal_url', $service['trusted_portal_url']), '/') }}/module-bridge.js"></script>
    <script src="{{ asset('js/meditrack.js') }}?v={{ filemtime(public_path('js/meditrack.js')) }}"></script>
</body>
</html>
