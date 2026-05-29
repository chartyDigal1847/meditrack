<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Student::query()->orderBy('last_name')->orderBy('first_name');
        if ($q = $request->input('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('student_number', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $perPage = (int) $request->input('per_page', 200);
        $students = $query->select(['id', 'student_number', 'first_name', 'last_name', 'grade_level', 'section', 'email', 'external_id'])->limit($perPage)->get();

        $usePortal = $request->boolean('portal') || $students->isEmpty();

        // If the caller explicitly requested the external DB, query the read-only
        // deoris connection directly (phpMyAdmin target). This is read-only and
        // does not modify the main database.
        if ($request->boolean('external_db')) {
            try {
                $query = DB::connection('deoris')->table('users')->where('role', 'student');
                if ($q = $request->input('q')) {
                    $query->where(function ($qry) use ($q) {
                        $qry->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('student_number', 'like', "%{$q}%");
                    });
                }
                $rows = $query->select(['id', 'name', 'email', 'student_number'])->limit($perPage)->get();
                $external = collect($rows)->map(function ($r) {
                    return [
                        'id' => null,
                        'external_id' => (string) ($r->id ?? ''),
                        'student_number' => (string) ($r->student_number ?? $r->id ?? ''),
                        'first_name' => null,
                        'last_name' => null,
                        'grade_level' => null,
                        'section' => null,
                        'email' => $r->email ?? null,
                    ];
                })->values();

                $this->audit->log($request, 'students.index.external_db');
                return $this->ok($external);
            } catch (\Exception $e) {
                // Fall back to local students on error
            }
        }

        // If the caller requested portal users or there are no local students,
        // try to fetch from the DEORIS portal using the SSO token saved in
        // session during the SSO exchange. This performs an authenticated
        // server-side request and does not modify the main database.
        if ($usePortal && session('sso_portal_token')) {
            $portalUrl = rtrim((string) config('app.portal_url', config('meditrack.trusted_portal_url', 'https://deoris.test')), '/');
            $http = Http::withToken(session('sso_portal_token'))->withHeaders(['Accept' => 'application/json']);
            if (Str::endsWith(parse_url($portalUrl, PHP_URL_HOST) ?? '', '.test') || app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            try {
                $resp = $http->get($portalUrl . '/api/v1/students');
                if ($resp->ok()) {
                    $portalList = $resp->json() ?? [];
                    // Normalize structure if wrapped under data
                    if (isset($portalList['data'])) $portalList = $portalList['data'];
                    $portalStudents = collect($portalList)->map(function ($s) {
                        return [
                            'id' => $s['id'] ?? null,
                            'student_number' => $s['student_number'] ?? ($s['id'] ?? null),
                            'first_name' => $s['first_name'] ?? ($s['name'] ?? ''),
                            'last_name' => $s['last_name'] ?? '',
                            'grade_level' => $s['grade_level'] ?? '',
                            'section' => $s['section'] ?? '',
                            'email' => $s['email'] ?? '',
                            'external_id' => $s['external_id'] ?? ($s['id'] ?? null),
                        ];
                    })->filter(fn($s) => $s['external_id'] !== null)->values();

                    // Merge local and portal lists, prefer local entries by id.
                    $combined = $students->keyBy('id')->union($portalStudents->keyBy('external_id'))->values();
                    $this->audit->log($request, 'students.index.portal');
                    return $this->ok($combined);
                }
            } catch (\Exception $e) {
                // Best-effort: fall back to local students if portal call fails.
            }
        }

        $this->audit->log($request, 'students.index');
        return $this->ok($students);
    }
}
