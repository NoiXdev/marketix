<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\MailerController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectMemberController;
use App\Http\Controllers\Admin\StorageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserProjectController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectChooserController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PasskeyManagementController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PixelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\TwoFactorPasskeyController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\UrlController;
use App\Http\Middleware\ProjectBindingMiddleware;
use Illuminate\Support\Facades\Route;

// Public marker used by the domain reachability check to confirm the
// Traefik -> app path resolves to this application.
Route::get('/.well-known/marketix', fn () => response()->json(['app' => 'marketix']))
    ->name('marketix.signature');

Route::group(['domain' => config('app.domain')], function () {
    // Root dispatcher: guests → login; one project → straight in;
    // zero or many → the project chooser.
    Route::get('/', function () {
        if (! auth()->check()) {
            return redirect()->route('app.auth.show-login');
        }

        $projects = auth()->user()->accessibleProjects()->get();

        if ($projects->count() === 1) {
            return redirect()->route('app.project.dashboard', $projects->first());
        }

        return redirect()->route('app.projects.choose');
    })->name('app.root');

    Route::post('/locale', [LocaleController::class, 'update'])->name('app.locale.update');

    // Guest-only routes
    Route::middleware('guest')->group(function () {
        Route::get('/auth/login', [AuthController::class, 'showLogin'])->name('app.auth.show-login');
        Route::post('/auth/login', [AuthController::class, 'login'])->name('app.auth.login');
        Route::get('/auth/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('app.auth.two-factor.show');
        Route::post('/auth/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:6,1')->name('app.auth.two-factor.store');
        Route::get('/auth/two-factor-challenge/passkey/options', [TwoFactorPasskeyController::class, 'options'])->middleware('throttle:6,1')->name('app.auth.two-factor.passkey-options');
        Route::post('/auth/two-factor-challenge/passkey', [TwoFactorPasskeyController::class, 'verify'])->middleware('throttle:6,1')->name('app.auth.two-factor.passkey');
        Route::get('/auth/forgot-password', [PasswordResetController::class, 'showForgot'])->name('app.auth.show-forgot');
        Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendLink'])->name('app.auth.forgot');
        Route::get('/auth/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('app.auth.show-reset');
        Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->name('app.auth.reset');
    });

    // Auth-only routes
    Route::middleware('auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('app.auth.logout');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('app.profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('app.profile.update');
        Route::post('/profile/two-factor', [TwoFactorController::class, 'enable'])->name('app.profile.two-factor.enable');
        Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('app.profile.two-factor.confirm');
        Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('app.profile.two-factor.disable');
        Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('app.profile.two-factor.recovery-codes');
        Route::patch('/user/passkeys/{passkey}/name', [PasskeyManagementController::class, 'rename'])->name('app.passkeys.rename');
        Route::get('/password/change', [ForcePasswordChangeController::class, 'show'])->name('app.password.change.show');
        Route::put('/password/change', [ForcePasswordChangeController::class, 'update'])->name('app.password.change.update');
        Route::get('/projects', [ProjectChooserController::class, 'index'])->name('app.projects.choose');
    });

    // Project tenant routes
    Route::middleware(['auth', ProjectBindingMiddleware::class])
        ->prefix('/project/{project}')
        ->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'show'])->name('app.project.dashboard');
            Route::get('/activity', [ActivityController::class, 'index'])->name('app.project.activity.index');
            Route::get('/statistics', [StatisticsController::class, 'show'])->name('app.project.statistics');
            Route::get('/reports/download', [ReportController::class, 'downloadProject'])->name('app.project.reports.download');

            // Links
            Route::get('/links', [UrlController::class, 'index'])->name('app.project.links.index');
            Route::get('/links/create', [UrlController::class, 'create'])->name('app.project.links.create');
            Route::post('/links', [UrlController::class, 'store'])->name('app.project.links.store');
            Route::get('/links/{url}', [UrlController::class, 'show'])->name('app.project.links.show');
            Route::get('/links/{url}/reports/download', [ReportController::class, 'downloadLink'])->name('app.project.links.reports.download');
            Route::get('/links/{url}/edit', [UrlController::class, 'edit'])->name('app.project.links.edit');
            Route::put('/links/{url}', [UrlController::class, 'update'])->name('app.project.links.update');
            Route::patch('/links/{url}/toggle-status', [UrlController::class, 'toggleStatus'])->name('app.project.links.toggle-status');
            Route::delete('/links/{url}', [UrlController::class, 'destroy'])->name('app.project.links.destroy');

            // Domains
            Route::get('/domains', [DomainController::class, 'index'])->name('app.project.domains.index');
            Route::get('/domains/create', [DomainController::class, 'create'])->name('app.project.domains.create');
            Route::post('/domains', [DomainController::class, 'store'])->name('app.project.domains.store');
            Route::get('/domains/{domain}/edit', [DomainController::class, 'edit'])->name('app.project.domains.edit');
            Route::put('/domains/{domain}', [DomainController::class, 'update'])->name('app.project.domains.update');
            Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('app.project.domains.destroy');
            Route::post('/domains/{domain}/check', [DomainController::class, 'check'])->name('app.project.domains.check');

            // QR Codes
            Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('app.project.qrcodes.index');
            Route::get('/qr-codes/create', [QrCodeController::class, 'create'])->name('app.project.qrcodes.create');
            Route::post('/qr-codes', [QrCodeController::class, 'store'])->name('app.project.qrcodes.store');
            Route::get('/qr-codes/{qrCode}/edit', [QrCodeController::class, 'edit'])->name('app.project.qrcodes.edit');
            Route::put('/qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('app.project.qrcodes.update');
            Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy'])->name('app.project.qrcodes.destroy');

            // Pixels
            Route::get('/pixels', [PixelController::class, 'index'])->name('app.project.pixels.index');
            Route::get('/pixels/create', [PixelController::class, 'create'])->name('app.project.pixels.create');
            Route::post('/pixels', [PixelController::class, 'store'])->name('app.project.pixels.store');
            Route::get('/pixels/{pixel}/edit', [PixelController::class, 'edit'])->name('app.project.pixels.edit');
            Route::put('/pixels/{pixel}', [PixelController::class, 'update'])->name('app.project.pixels.update');
            Route::delete('/pixels/{pixel}', [PixelController::class, 'destroy'])->name('app.project.pixels.destroy');

            // Team (project admins only)
            Route::middleware('project_admin')->group(function () {
                Route::get('/team', [TeamController::class, 'index'])->name('app.project.team.index');
                Route::post('/team/invitations', [TeamController::class, 'storeInvitation'])->name('app.project.team.invitations.store');
                Route::delete('/team/invitations/{invitation}', [TeamController::class, 'destroyInvitation'])->name('app.project.team.invitations.destroy');
                Route::post('/team/invitations/{invitation}/resend', [TeamController::class, 'resendInvitation'])
                    ->middleware('throttle:20,1')
                    ->name('app.project.team.invitations.resend');
                Route::patch('/team/members/{user}', [TeamController::class, 'updateMember'])->name('app.project.team.members.update');
                Route::delete('/team/members/{user}', [TeamController::class, 'destroyMember'])->name('app.project.team.members.destroy');
            });
        });
    // Invitation acceptance (public — token is the credential)
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('app.invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('app.invitations.accept');

    // Super-admin area
    Route::middleware(['auth', 'super_admin'])->prefix('/admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('app.admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('app.admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('app.admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('app.admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('app.admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('app.admin.users.destroy');
        Route::post('/users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])->name('app.admin.users.send-password-reset');

        Route::post('/users/{user}/projects', [UserProjectController::class, 'store'])->name('app.admin.users.projects.store');
        Route::patch('/users/{user}/projects/{project}', [UserProjectController::class, 'update'])->name('app.admin.users.projects.update');
        Route::delete('/users/{user}/projects/{project}', [UserProjectController::class, 'destroy'])->name('app.admin.users.projects.destroy');

        Route::get('/projects', [ProjectController::class, 'index'])->name('app.admin.projects.index');
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('app.admin.projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('app.admin.projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('app.admin.projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('app.admin.projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('app.admin.projects.destroy');

        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->name('app.admin.projects.members.store');
        Route::patch('/projects/{project}/members/{user}', [ProjectMemberController::class, 'update'])->name('app.admin.projects.members.update');
        Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('app.admin.projects.members.destroy');

        Route::get('/mailer', [MailerController::class, 'edit'])->name('app.admin.mailer.edit');
        Route::put('/mailer', [MailerController::class, 'update'])->name('app.admin.mailer.update');
        Route::post('/mailer/test', [MailerController::class, 'test'])->name('app.admin.mailer.test');

        Route::get('/branding', [BrandingController::class, 'edit'])->name('app.admin.branding.edit');
        Route::post('/branding', [BrandingController::class, 'update'])->name('app.admin.branding.update');

        Route::get('/storage', [StorageController::class, 'edit'])->name('app.admin.storage.edit');
        Route::put('/storage', [StorageController::class, 'update'])->name('app.admin.storage.update');
        Route::post('/storage/test', [StorageController::class, 'test'])->name('app.admin.storage.test');

        Route::get('/activity', [AdminActivityController::class, 'index'])->name('app.admin.activity.index');
    });
});

// URL shortener — handles requests on custom short-link domains
Route::post('/{slug}', [RedirectController::class, 'checkPassword'])
    ->middleware('throttle:10,1')
    ->name('redirect.password.check');
Route::fallback([RedirectController::class, 'handle']);
