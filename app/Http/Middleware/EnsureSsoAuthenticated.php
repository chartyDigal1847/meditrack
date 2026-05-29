<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureSsoAuthenticated Middleware
 *
 * Enforces that all requests have valid SSO context from DEORIS portal.
 *
 * Security Model:
 * - Every request must have been established through portal SSO
 * - Session must contain sso_id, sso_role, sso_email
 * - Prevents traditional Laravel auth bypass
 * - Protects against direct URL access without authentication
 *
 * Fallback to headers:
 * - If SSO session unavailable, tries EnsurePortalAuthenticated fallback
 *
 * Flow:
 * 1. Check if request has valid SSO session context
 * 2. If missing, abort with 401 Unauthorized
 * 3. If present, hydrate request with user context
 *
 * Allowed exceptions:
 * - /api/sso/exchange - Token exchange endpoint (no session yet)
 * - /api/sso/heartbeat - Session check (may have no session)
 * - Static assets (CSS, JS, images)
 */
class EnsureSsoAuthenticated
{
    /**
     * List of routes that don't require SSO authentication.
     * These are handled specially or are public endpoints.
     */
    protected $except = [
        // SSO token exchange - no session yet
        'api/sso/exchange',
        'api/sso/heartbeat',
        // Federated search - uses bearer token
        'api/search',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip auth check for excepted routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Try SSO session first
        if ($this->hasSsoContext($request)) {
            $this->hydrateUserContext($request);
            return $next($request);
        }

        // Fallback to header-based auth for internal/gateway requests and testing
        if ($request->header(config('meditrack.portal_token_header'))
            || $request->header('X-Portal-User-Id')
            || $request->header(config('meditrack.user_header'))
        ) {
            // Delegate to EnsurePortalAuthenticated for header parsing
            return app(EnsurePortalAuthenticated::class)->handle($request, $next);
        }

        // No valid auth found
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'SSO authentication required. No valid session or token found.',
            ], 401);
        }

        return redirect('/');
    }

    /**
     * Check if route should skip SSO authentication.
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if request has valid SSO context in session.
     */
    protected function hasSsoContext(Request $request): bool
    {
        try {
            $session = $request->session();
        } catch (\RuntimeException $e) {
            return false;
        }

        return $session->has('sso_id')
            && $session->has('sso_role')
            && $session->has('sso_email')
            && !empty($session->get('sso_id'));
    }

    /**
     * Hydrate request with user context from session.
     */
    protected function hydrateUserContext(Request $request): void
    {
        $session = $request->session();
        $ssoId = $session->get('sso_id');
        $role = $session->get('sso_role');
        $email = $session->get('sso_email');
        $name = $session->get('sso_name', 'DEORIS User');

        $request->attributes->set('sso_id', $ssoId);
        $request->attributes->set('meditrack_role', $role);
        $request->attributes->set('portal_token_present', true);
    }
}
