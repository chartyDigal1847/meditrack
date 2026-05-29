<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\DeorisUserService;

class EnsurePortalAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $portalUser = json_decode((string) $request->header(config('meditrack.user_header'), '{}'), true) ?: [];

        $role = strtolower((string) (
            $portalUser['role']
            ?? $request->header(config('meditrack.role_header'))
            ?? $request->header('X-Portal-User-Role')
            ?? 'student'
        ));
        $role = match ($role) {
            'nurse', 'clinic_nurse', 'health_officer' => 'nurse',
            'admin', 'administrator' => 'admin',
            default => 'student',
        };

        $externalId = (string) ($portalUser['id'] ?? $request->header('X-DEORIS-User-Id') ?? $request->header('X-Portal-User-Id', ''));

        if ($externalId === '') {
            return response()->json([
                'success' => false,
                'message' => 'DEORIS user identity is required.',
            ], 401);
        }

        $name = (string) ($portalUser['name'] ?? $request->header('X-DEORIS-Name') ?? $request->header('X-Portal-User-Name', 'DEORIS User'));
        $email = (string) ($portalUser['email'] ?? $request->header('X-DEORIS-Email') ?? $request->header('X-Portal-User-Email', ''));

        $user = new GenericUser([
            'id' => $externalId,
            'external_id' => $externalId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => '',
            'remember_token' => '',
        ]);

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('meditrack_role', $role);
        $request->attributes->set('portal_token_present', $request->headers->has(config('meditrack.portal_token_header')));

        // Sync user to local database (non-blocking)
        try {
            (new DeorisUserService())->syncByRole($role, $externalId, $email, $name);
        } catch (\Exception $e) {
            \Log::warning('[MediTrack] User sync failed', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
