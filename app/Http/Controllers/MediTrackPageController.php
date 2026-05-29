<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DeorisUserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MediTrackPageController extends Controller
{
    public function dashboard()
    {
        return view('meditrack', ['service' => config('meditrack')]);
    }

    public function ssoRedirect(Request $request)
    {
        $role = strtolower((string) $request->input('role', 'student'));
        $role = match ($role) {
            'nurse', 'clinic_nurse', 'health_officer' => 'nurse',
            'admin', 'administrator' => 'admin',
            default => 'student',
        };

        $id = $request->input('id', '');
        $name = $request->input('name', 'DEORIS User');
        $email = $request->input('email', '');
        $embedded = $request->input('embedded') === '1';

        if (empty($id)) {
            return response()->json([
                'success' => false,
                'message' => 'DEORIS user identity (ID) is required.'
            ], 400);
        }

        // Establish local session context
        $request->session()->flush();
        session([
            'sso_id' => $id,
            'sso_role' => $role,
            'sso_name' => $name,
            'sso_email' => $email,
            'sso_embedded' => $embedded,
            'sso_authenticated_at' => now()->timestamp,
        ]);

        // Sync user locally (e.g., matching student/nurse tables)
        try {
            (new DeorisUserService())->syncByRole($role, $id, $email, $name);
        } catch (\Exception $e) {
            \Log::warning('[MediTrack] User sync failed during SSO', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('meditrack.dashboard'),
            'user' => [
                'id' => (string) $id,
                'name' => (string) $name,
                'email' => (string) $email,
                'role' => $role,
            ],
        ]);
    }

    public function ssoExchange(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'embedded' => 'sometimes|boolean',
        ]);

        $portalUrl = rtrim((string) config('app.portal_url', config('meditrack.trusted_portal_url', 'https://deoris.test')), '/');
        $http = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $validated['token'],
        ]);

        // When calling a local/testing portal (e.g. *.test) disable TLS verification
        // to avoid cURL error 60 in local dev. This change is scoped to the meditrack
        // module only.
        if (Str::endsWith(parse_url($portalUrl, PHP_URL_HOST) ?? '', '.test') || app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        // Save the incoming portal token in session so we can proxy portal API
        // requests on behalf of the current user from the server side. This
        // keeps main DB untouched and confines calls to portal APIs via HTTP.
        session(['sso_portal_token' => $validated['token']]);

        $response = $http->post($portalUrl . '/api/v1/sso/exchange', [
            'token' => $validated['token'],
        ]);

        if (! $response->ok()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid SSO token.'
            ], 401);
        }

        $data = $response->json();
        $user = $data['user'] ?? $data['data']['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid SSO response.'
            ], 401);
        }

        $role = strtolower((string) ($user['role'] ?? 'student'));
        $role = match ($role) {
            'nurse', 'clinic_nurse', 'health_officer' => 'nurse',
            'admin', 'administrator' => 'admin',
            default => 'student',
        };

        $id = (string) $user['id'];
        $name = (string) ($user['name'] ?? 'DEORIS User');
        $email = (string) ($user['email'] ?? '');
        $embedded = (bool) ($validated['embedded'] ?? false);

        $request->session()->flush();
        session([
            'sso_id' => $id,
            'sso_role' => $role,
            'sso_name' => $name,
            'sso_email' => $email,
            'sso_embedded' => $embedded,
            'sso_authenticated_at' => now()->timestamp,
        ]);

        try {
            (new DeorisUserService())->syncByRole($role, $id, $email, $name);
        } catch (\Exception $e) {
            \Log::warning('[MediTrack] User sync failed during SSO exchange', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ],
            'redirect' => route('meditrack.dashboard')
        ]);
    }
}
