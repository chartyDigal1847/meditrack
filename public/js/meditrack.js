(function () {
    "use strict";

    var cfg = window.MEDITRACK || {};
    var state = {
        user: { id: "", name: "DEORIS User", email: "", role: "student" },
        role: "student",
        data: {},
        page: "dashboard",
    };

    function $(id) { return document.getElementById(id); }
    function qsa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }
    function esc(v) {
        return String(v == null ? "" : v).replace(/[&<>"']/g, function (c) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[c];
        });
    }
    function date(v) { return v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "-"; }
    function fullName(student) { return student ? (student.first_name + " " + student.last_name) : "Unassigned"; }
    function canMutate() { return state.role === "nurse"; }

    function headers() {
        return {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') || {}).content || "",
            "X-DEORIS-Role": state.role,
            "X-DEORIS-User": JSON.stringify(state.user),
            "X-Correlation-ID": crypto.randomUUID ? crypto.randomUUID() : String(Date.now()),
        };
    }

    async function api(path, options) {
        var res = await fetch(cfg.apiBase + path, Object.assign({ credentials: "include", headers: headers() }, options || {}));
        var json = await res.json().catch(function () { return {}; });
        if (!res.ok || json.success === false) {
            var details = json.errors ? Object.keys(json.errors).map(function (key) { return json.errors[key][0]; }).join(" ") : "";
            throw new Error(details || json.message || "Please check the form and try again.");
        }
        return json.data;
    }

    function toast(message, type) {
        var el = document.createElement("div");
        el.className = "toast " + (type || "info");
        el.textContent = message;
        $("toastContainer").appendChild(el);
        setTimeout(function () { el.remove(); }, 3600);
    }

    function setPage(page) {
        state.page = page;
        qsa(".page").forEach(function (el) { el.classList.toggle("active", el.id === "page-" + page); });
        qsa("[data-page]").forEach(function (el) { el.classList.toggle("active", el.dataset.page === page); });
        var titles = {
            dashboard: "Dashboard",
            visits: "Visit History",
            records: "Medical Records",
            reports: "Health Reports",
            alerts: "Emergency Alerts",
            forms: "Clinic Forms",
            student: "Student Health",
            manage: "Manage",
        };
        var title = document.querySelector(".module-title");
        if (title) title.textContent = titles[page] || "Dashboard";
    }

    function applyRole() {
        $("userName").textContent = state.user.name || "Portal User";
        $("userRole").textContent = state.role;
        $("userInitial").textContent = (state.user.name || "U").charAt(0).toUpperCase();

        var isNurse = state.role === "nurse";
        var isAdmin = state.role === "admin";
        var isStudent = state.role === "student";

        qsa(".nurse-only").forEach(function (el) { el.classList.toggle("hidden", !isNurse); });
        qsa(".admin-only").forEach(function (el) { el.classList.toggle("hidden", !isAdmin); });
        qsa(".student-only").forEach(function (el) { el.classList.toggle("hidden", !isStudent); });
        qsa(".student-hidden").forEach(function (el) { el.classList.toggle("hidden", isStudent); });

        var copy = {
            nurse: "Nurse mode: full clinical management is enabled and every medical update is audited.",
            student: "Student mode: you may view your own records and submit health concerns only.",
            admin: "Admin mode: read-only analytics, monitoring, and audit access.",
        };
        $("roleNotice").textContent = copy[state.role] || copy.student;
    }

    function metric(icon, value, label) {
        return '<div class="metric-card"><div class="metric-icon"><i class="fa-solid ' + icon + '"></i></div><div class="metric-value">' + esc(value) + '</div><div class="metric-label">' + esc(label) + '</div></div>';
    }

    function item(title, meta, badge) {
        return '<div class="list-item"><div><div class="item-title">' + esc(title) + '</div><div class="item-meta">' + esc(meta || "") + '</div></div>' + (badge ? '<span class="badge ' + esc(badge) + '">' + esc(badge.replaceAll("_", " ")) + '</span>' : '') + '</div>';
    }

    function renderMetrics() {
        var m = state.data.metrics || {};
        if (state.role === "student") {
            $("metricsGrid").innerHTML =
                metric("fa-notes-medical", m.records || 0, "Medical Records") +
                metric("fa-virus", m.diagnoses || 0, "Diagnoses") +
                metric("fa-prescription-bottle-medical", m.prescriptions || 0, "Prescriptions");
        } else {
            $("metricsGrid").innerHTML =
                metric("fa-user-graduate", m.students || 0, "Students") +
                metric("fa-stethoscope", m.visits_today || 0, "Visits Today") +
                metric("fa-notes-medical", m.records || 0, "Medical Records") +
                metric("fa-triangle-exclamation", m.active_alerts || 0, "Active Alerts") +
                metric("fa-virus", m.diagnoses || 0, "Diagnoses") +
                metric("fa-prescription-bottle-medical", m.prescriptions || 0, "Prescriptions") +
                metric("fa-hourglass-half", m.open_visits || 0, "Open Visits") +
                metric("fa-message-medical", m.pending_concerns || 0, "Pending Concerns");
        }
    }

    function renderVisits() {
        var visits = state.data.clinic_visits || [];
        $("recentVisits").innerHTML = visits.length ? visits.slice(0, 6).map(function (v) {
            return item(fullName(v.student), (v.chief_complaint || "") + " | " + date(v.checked_in_at), v.status);
        }).join("") : '<div class="empty">No clinic visits yet.</div>';

        $("visitRows").innerHTML = visits.length ? visits.map(function (v) {
            var actions = '<button class="mini" data-view-visit="' + v.id + '">View</button>';
            if (canMutate()) {
                actions += ' <button class="mini btn-danger" data-delete-visit="' + v.id + '">Delete</button>';
            }
            return '<tr><td><strong>' + esc(fullName(v.student)) + '</strong><div class="item-meta">' + esc(v.student ? v.student.student_number : "") + '</div></td><td>' + esc(v.chief_complaint) + '</td><td><span class="badge ' + esc(v.status) + '">' + esc(v.status.replaceAll("_", " ")) + '</span></td><td><span class="badge ' + esc(v.severity) + '">' + esc(v.severity) + '</span></td><td>' + esc(v.nurse ? v.nurse.name : "-") + '</td><td>' + date(v.checked_in_at) + '</td><td>' + actions + '</td></tr>';
        }).join("") : '<tr><td colspan="7"><div class="empty">No visits found.</div></td></tr>';
    }

    function renderRecords() {
        $("recordList").innerHTML = (state.data.records || []).map(function (r) {
            return item(r.title, (r.record_type || "record") + " | " + fullName(r.student), r.status);
        }).join("") || '<div class="empty">No records yet.</div>';
        $("diagnosisList").innerHTML = (state.data.diagnosis_trends || []).map(function (d) {
            return item(d.title, d.total + " recorded case(s)", "diagnosed");
        }).join("") || '<div class="empty">No diagnoses yet.</div>';
        $("prescriptionList").innerHTML = (state.data.prescriptions || []).map(function (p) {
            return item(p.medication_name, p.dosage + " | " + p.frequency, p.status);
        }).join("") || '<div class="empty">No prescriptions yet.</div>';
    }

    function renderAlerts() {
        var alerts = state.data.alerts || [];
        $("alertList").innerHTML = alerts.slice(0, 5).map(function (a) {
            return item(a.title, (a.message || "").slice(0, 90), a.severity);
        }).join("") || '<div class="empty">No active emergency alerts.</div>';
        $("alertBoard").innerHTML = alerts.map(function (a) {
            return '<article class="metric-card alert-tile"><div class="item-title">' + esc(a.title) + '</div><div class="item-meta">' + esc(a.message) + '</div><p><span class="badge ' + esc(a.severity) + '">' + esc(a.severity) + '</span></p><div class="item-meta">' + date(a.issued_at) + '</div></article>';
        }).join("") || '<div class="empty">No alerts issued.</div>';
    }

    function renderReports() {
        var trends = state.data.diagnosis_trends || [];
        var max = trends.reduce(function (n, t) { return Math.max(n, Number(t.total || 0)); }, 1);
        var trendsHtml = trends.map(function (t) {
            var pct = Math.round((Number(t.total || 0) / max) * 100);
            return '<div class="bar-row"><strong>' + esc(t.title) + '</strong><div class="bar-track"><div class="bar-fill" style="width:' + pct + '%"></div></div><span>' + esc(t.total) + '</span></div>';
        }).join("") || '<div class="empty">No trend data yet.</div>';

        var reportsHtml = (state.data.reports || []).map(function (r) {
            return item(r.title, (r.report_type || "report") + " | " + date(r.generated_at), r.status);
        }).join("") || '<div class="empty">No generated reports yet.</div>';

        if ($("trendBars")) $("trendBars").innerHTML = trendsHtml;
        if ($("trendBarsDashboard")) $("trendBarsDashboard").innerHTML = trendsHtml;
        if ($("reportList")) $("reportList").innerHTML = reportsHtml;
        if ($("reportListDashboard")) $("reportListDashboard").innerHTML = reportsHtml;
    }

    function renderOther() {
        $("concernList").innerHTML = (state.data.concerns || []).map(function (c) {
            return item(c.title, c.description, c.status);
        }).join("") || '<div class="empty">No student concerns yet.</div>';
        $("notificationList").innerHTML = (state.data.notifications || []).map(function (n) {
            return item(n.title, n.message, n.type);
        }).join("") || '<div class="empty">No notifications.</div>';
        $("activityList").innerHTML = (state.data.activity || []).map(function (a) {
            return item(a.message, date(a.at), a.type || "active");
        }).join("") || '<div class="empty">No activity yet.</div>';
        $("auditRows").innerHTML = (state.data.audit_logs || []).map(function (a) {
            return '<tr><td>' + esc(a.actor_name || "-") + '</td><td>' + esc(a.actor_role) + '</td><td>' + esc(a.action) + '</td><td>' + esc((a.auditable_type || "").split("\\").pop() || "-") + '</td><td>' + date(a.created_at) + '</td></tr>';
        }).join("") || '<tr><td colspan="5"><div class="empty">No audit entries.</div></td></tr>';
    }

    function renderAll() {
        applyRole();
        renderMetrics();
        renderVisits();
        renderRecords();
        renderAlerts();
        renderReports();
        renderOther();
    }

    async function load() {
        $("loaderText").textContent = "Loading service data.";
        state.data = await api("/bootstrap");
        renderAll();
        $("loader").classList.add("hidden");
        $("workspace").classList.remove("hidden");
    }

    function modal(title, body, onSubmit) {
        $("modalHost").innerHTML = '<div class="overlay"><form class="modal" id="activeModal"><div class="modal-head"><h3>' + esc(title) + '</h3><button class="close-x" type="button" data-close-modal>&times;</button></div><div class="modal-body">' + body + '<div class="modal-actions"><button class="btn btn-secondary" type="button" data-close-modal>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div></div></form></div>';
        $("activeModal").addEventListener("submit", async function (e) {
            e.preventDefault();
            try { await onSubmit(new FormData(e.currentTarget), e.currentTarget); $("modalHost").innerHTML = ""; toast("Saved.", "success"); await load(); }
            catch (err) { toast(err.message, "error"); }
        });
    }

    function field(name, label, type, required) {
        return fieldEx({ name: name, label: label, type: type, required: required });
    }
    function fieldEx(opts) {
        var attrs = '';
        attrs += opts.required ? ' required' : '';
        attrs += opts.placeholder ? ' placeholder="' + esc(opts.placeholder) + '"' : '';
        attrs += opts.value != null ? ' value="' + esc(opts.value) + '"' : '';
        attrs += opts.min != null ? ' min="' + esc(opts.min) + '"' : '';
        attrs += opts.max != null ? ' max="' + esc(opts.max) + '"' : '';
        attrs += opts.step != null ? ' step="' + esc(opts.step) + '"' : '';
        return '<div class="form-group"><label>' + esc(opts.label) + '</label><input class="field" name="' + esc(opts.name) + '" type="' + esc(opts.type || "text") + '"' + attrs + '>' + (opts.help ? '<small class="field-help">' + esc(opts.help) + '</small>' : '') + '</div>';
    }
    function textEx(opts) {
        return '<div class="form-group"><label>' + esc(opts.label) + '</label><textarea class="field" name="' + esc(opts.name) + '"' + (opts.required ? " required" : "") + (opts.placeholder ? ' placeholder="' + esc(opts.placeholder) + '"' : '') + '>' + esc(opts.value || "") + '</textarea>' + (opts.help ? '<small class="field-help">' + esc(opts.help) + '</small>' : '') + '</div>';
    }
    function text(name, label, required) { return '<div class="form-group"><label>' + label + '</label><textarea class="field" name="' + name + '"' + (required ? " required" : "") + '></textarea></div>'; }
    function select(name, label, options, selected, help) {
        return '<div class="form-group"><label>' + esc(label) + '</label><select class="field" name="' + esc(name) + '">' + options.map(function (o) { return '<option value="' + esc(o) + '"' + (o === selected ? " selected" : "") + '>' + esc(o.replaceAll("_", " ")) + '</option>'; }).join("") + '</select>' + (help ? '<small class="field-help">' + esc(help) + '</small>' : '') + '</div>';
    }
    function formObject(fd) {
        var data = {};
        fd.forEach(function (value, key) {
            var clean = String(value == null ? "" : value).trim();
            if (clean !== "") data[key] = clean;
        });
        return data;
    }
    function value(fd, key) { return String(fd.get(key) || "").trim(); }

    async function openVisit() {
        try {
            var students = await api('/students?external_db=1');
        } catch (err) {
            toast('Unable to load students: ' + err.message, 'error');
            return;
        }

        var options = students.length ? students.map(function (s) {
            var val = s.external_id || s.id || s.student_number || '';
            var idNum = s.student_number || (s.external_id || s.id || '');
            var email = s.email || '-';
            return '<option value="' + esc(val) + '">' + esc(email) + ' | ' + esc(idNum) + '</option>';
        }).join('') : '';

        var studentHtml = '';
        if (options) {
            studentHtml = '<div class="form-section-title">Student details</div>' +
                '<div class="modal-grid">' +
                    '<div class="form-group"><label>Select Student</label><select class="field" name="student_id" required>' + options + '</select></div>' +
                '</div>';
        } else {
            studentHtml = '<div class="form-section-title">Student details</div>' +
                '<div class="modal-grid"><div class="empty">No students found in MediTrack database. Please sync students or create one first.</div></div>';
        }

        modal('Record Clinic Visit',
            studentHtml +
            '<div class="form-section-title">Visit details</div>' +
            fieldEx({ name: 'chief_complaint', label: 'Chief Complaint', required: true, placeholder: 'Headache and dizziness after PE class', help: 'Briefly describe what the student is reporting.' }) +
            '<div class="modal-grid">' +
                select('status', 'Status', ['under_evaluation', 'diagnosed', 'treated', 'referred', 'emergency'], 'under_evaluation', 'Choose the current visit progress.') +
                select('severity', 'Severity', ['low', 'medium', 'high', 'emergency'], 'low', 'Use emergency only for urgent clinic escalation.') +
                fieldEx({ name: 'temperature', label: 'Temperature', type: 'number', placeholder: '37.4', min: 30, max: 45, step: '0.1', help: 'Celsius, optional.' }) +
                fieldEx({ name: 'blood_pressure', label: 'Blood Pressure', placeholder: '120/80' }) +
                fieldEx({ name: 'pulse_rate', label: 'Pulse Rate', type: 'number', placeholder: '82', min: 20, max: 240 }) +
                fieldEx({ name: 'respiratory_rate', label: 'Respiratory Rate', type: 'number', placeholder: '18', min: 5, max: 80 }) +
            '</div>' +
            fieldEx({ name: 'diagnosis_title', label: 'Initial Diagnosis', placeholder: 'Possible dehydration' }) +
            textEx({ name: 'treatment_plan', label: 'Treatment Plan', placeholder: 'Rested in clinic, oral fluids given, guardian notified if symptoms persist.' }) +
            textEx({ name: 'notes', label: 'Nurse Notes', placeholder: 'Observed for 20 minutes. No vomiting. Student advised to return if headache worsens.' }),
            function (fd) {
                return api('/clinic-visits', { method: 'POST', body: JSON.stringify(formObject(fd)) });
            }
        );
    }

    async function openVisitDetail(id) {
        try {
            var visit = await api("/clinic-visits/" + encodeURIComponent(id));
            var student = visit.student || {};
            var readonly = !canMutate();
            modal("Visit Details",
                '<div class="visit-summary">' +
                    '<div><span>Student</span><strong>' + esc(fullName(student)) + '</strong><small>' + esc(student.student_number || "-") + '</small></div>' +
                    '<div><span>Complaint</span><strong>' + esc(visit.chief_complaint || "-") + '</strong><small>' + esc(date(visit.checked_in_at)) + '</small></div>' +
                    '<div><span>Nurse</span><strong>' + esc(visit.nurse ? visit.nurse.name : "-") + '</strong><small>' + esc(visit.visit_code || "") + '</small></div>' +
                '</div>' +
                (readonly ? '<div class="privacy-notice compact"><i class="fa-solid fa-lock"></i><span>Only nurses can update visit status.</span></div>' : '') +
                '<div class="modal-grid">' +
                    select("status", "Visit Status", ["pending_checkup", "under_evaluation", "diagnosed", "treated", "referred", "emergency"], visit.status, "Update this as the student moves through clinic care.") +
                    select("severity", "Severity", ["low", "medium", "high", "emergency"], visit.severity, "Reflect the current urgency of the complaint.") +
                '</div>' +
                textEx({ name: "notes", label: "Nurse Notes", value: visit.notes || "", placeholder: "Example: Symptoms improved after rest. Sent back to class with water break advice." }) +
                (readonly ? '<input type="hidden" name="_readonly" value="1">' : ''),
                function (fd, form) {
                    if (form.querySelector('[name="_readonly"]')) {
                        $("modalHost").innerHTML = "";
                        return Promise.resolve();
                    }
                    return api("/clinic-visits/" + encodeURIComponent(id), { method: "PUT", body: JSON.stringify(formObject(fd)) });
                }
            );
        } catch (err) {
            toast(err.message, "error");
        }
    }

    async function openRecord() {
        try {
            var students = await api('/students?external_db=1');
        } catch (err) {
            toast('Unable to load students: ' + err.message, 'error');
            return;
        }

        var options = students.length ? students.map(function (s) {
            var val = s.external_id || s.id || s.student_number || '';
            var idNum = s.student_number || (s.external_id || s.id || '');
            var email = s.email || '-';
            return '<option value="' + esc(val) + '">' + esc(email) + ' | ' + esc(idNum) + '</option>';
        }).join('') : '';

        var studentHtml = '';
        if (options) {
            studentHtml = '<div class="form-group"><label>Select Student</label><select class="field" name="student_id" required>' + options + '</select></div>';
        } else {
            studentHtml = '<div class="empty">No students found in database.</div>';
        }

        modal('Add Medical Record', studentHtml + field("record_type", "Record Type", "text", true) + field("title", "Title", "text", true) + text("summary", "Summary", true) + text("sensitive_notes", "Sensitive Notes"), function (fd) {
            return api("/medical-records", { method: "POST", body: JSON.stringify(formObject(fd)) });
        });
    }

    function openReport() {
        modal("Generate Health Report", field("report_type", "Report Type", "text", true) + field("title", "Title", "text", true) + field("period_start", "Period Start", "date") + field("period_end", "Period End", "date") + text("summary", "Summary", true), function (fd) {
            return api("/health-reports", { method: "POST", body: JSON.stringify(formObject(fd)) });
        });
    }

    async function openAlert() {
        try {
            var students = await api('/students?external_db=1');
        } catch (err) {
            toast('Unable to load students: ' + err.message, 'error');
            return;
        }

        var options = students.length ? students.map(function (s) {
            var val = s.external_id || s.id || s.student_number || '';
            var idNum = s.student_number || (s.external_id || s.id || '');
            var email = s.email || '-';
            return '<option value="' + esc(val) + '">' + esc(email) + ' | ' + esc(idNum) + '</option>';
        }).join('') : '';

        var studentHtml = '';
        if (options) {
            studentHtml = '<div class="form-group"><label>Select Student</label><select class="field" name="student_id" required>' + options + '</select></div>';
        } else {
            studentHtml = '<div class="empty">No students found in database.</div>';
        }

        modal("Issue Emergency Alert", studentHtml + select("severity", "Severity", ["high", "critical", "emergency"]) + field("title", "Title", "text", true) + text("message", "Message", true), function (fd) {
            return api("/emergency-alerts", { method: "POST", body: JSON.stringify(formObject(fd)) });
        });
    }

    async function openConcern() {
        var isNurse = state.role === "nurse";
        var studentHtml = '';

        if (isNurse) {
            try {
                var students = await api('/students?external_db=1');
            } catch (err) {
                toast('Unable to load students: ' + err.message, 'error');
                return;
            }

            var options = students.length ? students.map(function (s) {
                var val = s.external_id || s.id || s.student_number || '';
                var idNum = s.student_number || (s.external_id || s.id || '');
                var email = s.email || '-';
                return '<option value="' + esc(val) + '">' + esc(email) + ' | ' + esc(idNum) + '</option>';
            }).join('') : '';

            if (options) {
                studentHtml = '<div class="form-group"><label>Select Student</label><select class="field" name="student_id" required>' + options + '</select></div>';
            } else {
                studentHtml = '<div class="empty">No students found in database.</div>';
            }
        }

        modal("Report Health Concern", studentHtml + field("title", "Concern", "text", true) + select("severity", "Severity", ["low", "medium", "high"]) + text("description", "Description", true), function (fd) {
            return api("/student-concerns", { method: "POST", body: JSON.stringify(formObject(fd)) });
        });
    }

    async function openPrescription() {
        try {
            var students = await api('/students?external_db=1');
        } catch (err) {
            toast('Unable to load students: ' + err.message, 'error');
            return;
        }

        var options = students.length ? students.map(function (s) {
            var val = s.external_id || s.id || s.student_number || '';
            var idNum = s.student_number || (s.external_id || s.id || '');
            var email = s.email || '-';
            return '<option value="' + esc(val) + '">' + esc(email) + ' | ' + esc(idNum) + '</option>';
        }).join('') : '';

        var studentHtml = '';
        if (options) {
            studentHtml = '<div class="form-group"><label>Select Student</label><select class="field" name="student_id" required>' + options + '</select></div>';
        } else {
            studentHtml = '<div class="empty">No students found in database.</div>';
        }

        modal("Issue Prescription",
            studentHtml +
            field("medication_name", "Medication Name", "text", true) +
            field("dosage", "Dosage", "text", true) +
            field("frequency", "Frequency", "text", true) +
            field("duration", "Duration (e.g. 7 days)", "text") +
            text("instructions", "Instructions"),
            function (fd) {
                return api("/prescriptions", { method: "POST", body: JSON.stringify(formObject(fd)) });
            }
        );
    }

    function wire() {
        document.addEventListener("click", function (e) {
            var page = e.target.closest("[data-page]");
            if (page) setPage(page.dataset.page);
            if (e.target.closest("[data-refresh]")) load().then(function () { toast("Dashboard refreshed.", "info"); });
            if (e.target.closest("[data-open-visit]")) openVisit();
            if (e.target.closest("[data-open-record]")) openRecord();
            if (e.target.closest("[data-open-report]")) openReport();
            if (e.target.closest("[data-open-prescription]")) openPrescription();
            if (e.target.closest("[data-open-alert]")) openAlert();
            if (e.target.closest("[data-open-concern]")) openConcern();
            var visitButton = e.target.closest("[data-view-visit]");
            if (visitButton) openVisitDetail(visitButton.dataset.viewVisit);
            var deleteButton = e.target.closest("[data-delete-visit]");
            if (deleteButton) {
                var id = deleteButton.dataset.deleteVisit;
                if (!confirm("Delete this visit? This cannot be undone.")) return;
                api("/clinic-visits/" + encodeURIComponent(id), { method: "DELETE" }).then(function () {
                    toast("Visit deleted.", "success");
                    load();
                }).catch(function (err) { toast(err.message, "error"); });
            }
            if (e.target.closest("[data-close-modal]")) $("modalHost").innerHTML = "";
        });
        $("visitSearch").addEventListener("input", filterVisits);
        $("visitStatus").addEventListener("change", filterVisits);
    }

    function filterVisits() {
        var q = $("visitSearch").value.toLowerCase();
        var status = $("visitStatus").value;
        state.data.clinic_visits = (state.data.clinic_visits || []).sort(function (a, b) { return String(b.checked_in_at).localeCompare(String(a.checked_in_at)); });
        qsa("#visitRows tr").forEach(function (row) {
            var txt = row.textContent.toLowerCase();
            var show = (!q || txt.indexOf(q) !== -1) && (!status || txt.indexOf(status.replaceAll("_", " ")) !== -1);
            row.style.display = show ? "" : "none";
        });
    }

    window.addEventListener("module:ready", function (event) {
        wire();

        // Fast path: use portal-provided user identity when available and only
        // hydrate this module's local session. Fallback keeps token exchange
        // support for older portal bridges.
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.content : "";
        var portalUser = (event.detail && event.detail.user) || window.PORTAL_USER || null;
        var ssoToken = (event.detail && event.detail.token) || window.SSO_TOKEN || null;
        if (!ssoToken && !(portalUser && portalUser.id)) {
            $("loaderText").textContent = "SSO Error: Missing SSO identity.";
            toast("SSO Error: Missing SSO identity.", "error");
            return;
        }

        var url = portalUser && portalUser.id ? "/sso/redirect" : "/sso/exchange";
        var payload = portalUser && portalUser.id ? {
            id: portalUser.id,
            name: portalUser.name || "DEORIS User",
            email: portalUser.email || "",
            role: portalUser.role || "student",
            embedded: (event.detail && event.detail.embedded) ? "1" : "0"
        } : {
            token: ssoToken,
            embedded: !!(event.detail && event.detail.embedded)
        };

        fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            if (!res.ok) throw new Error("SSO session exchange failed: " + res.status);
            return res.json();
        })
        .then(function (json) {
            if (json && json.success && (json.user || portalUser)) {
                state.user = json.user || portalUser;
                state.role = state.user.role || "student";
                load().catch(function (err) {
                    $("loaderText").textContent = err.message;
                    toast(err.message, "error");
                });
            } else {
                throw new Error((json && json.message) || "Session exchange rejected.");
            }
        })
        .catch(function (err) {
            $("loaderText").textContent = "SSO Error: " + err.message;
            toast("SSO Error: " + err.message, "error");
        });
    });


    window.addEventListener("module:error", function (event) {
        var detail = event.detail || {};
        $("loaderText").textContent = detail.code === "not_embedded"
            ? "Open MediTrack from the DEORIS portal to continue."
            : "DEORIS sign-in could not be verified.";
    });
})();
