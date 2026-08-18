<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies the actions a shared demo account must not perform. The demo runs on
 * its own instance, so this is the second line of defence — but it is the only
 * thing standing between a visitor and locking every later visitor out.
 *
 * Matching is by ROUTE NAME, not HTTP method: two WebAuthn endpoints are GETs
 * that begin a registration ceremony and belong on the list. Page-rendering
 * routes are simply never listed, so the whole app stays browsable.
 */
class DemoGuard
{
    /** @var list<string> */
    public const BLOCKED_ROUTES = [
        // Lockout prevention — a visitor must not be able to add a second
        // factor or change the credentials of the shared account.
        'app.profile.two-factor.enable',
        'app.profile.two-factor.confirm',
        'app.profile.two-factor.disable',
        'app.profile.two-factor.recovery-codes',
        'passkey.store',
        'passkey.registration-options',
        'passkey.confirm',
        'passkey.confirm-options',
        'passkey.destroy',
        'app.passkeys.rename',
        // ProfileController::update changes only the password.
        'app.profile.update',

        // Access that would outlive the nightly reset.
        'app.profile.tokens.store',
        'app.profile.tokens.destroy',

        // Instance-global settings.
        'app.admin.mailer.update',
        'app.admin.mailer.test',
        'app.admin.storage.update',
        'app.admin.storage.test',
        'app.admin.branding.update',

        // Real infrastructure: Traefik config regeneration and DNS checks.
        'app.project.domains.store',
        'app.project.domains.update',
        'app.project.domains.destroy',
        'app.project.domains.check',

        // Outbound mail. Mail is already severed globally; denying here as
        // well lets the UI explain WHY the button is disabled.
        'app.project.team.invitations.store',
        'app.project.team.invitations.resend',
        'app.project.reports.send-now',
        'app.admin.users.send-password-reset',
        'app.auth.forgot',
        'app.auth.reset',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::BLOCKED_ROUTES, true)) {
            return back()->with('error', __('demo.blocked'));
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
