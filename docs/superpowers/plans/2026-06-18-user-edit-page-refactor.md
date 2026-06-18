# User Edit Page Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the admin user edit page into a sectioned page and add three capabilities: attaching a user to projects, an admin "Send Password Reset" action, and a "force password change on next login" flag enforced app-wide.

**Architecture:** Backend changes are additive — one migration, one model cast, a few controller methods, one new controller for user→project membership, one forced-change controller, and one global middleware. The user edit React page is rewritten into three cards (Account, Security actions, Project memberships) where project changes fire inline immediate requests (mirroring `Admin/Projects/Edit.tsx`). Authorization rides entirely on the existing `super_admin` middleware on the `/admin` group; the force-change gate runs in the `web` middleware group.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + TypeScript + Inertia.js, MariaDB (DDEV), PHPUnit feature tests with `Inertia\Testing\AssertableInertia`, Tailwind.

## Global Constraints

- All PHP/Composer/NPM commands MUST run through DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`.
- Ziggy `route()` calls (PHP and TS) MUST use object params: `route('name', { user: id })`, never a bare value.
- Frontend gate is `ddev npm run build` (TypeScript check + Vite). `npm run lint` is broken — do not rely on it.
- Tests run with `ddev php artisan test --filter=<Name>`. Use `Tests\TestCase` + `RefreshDatabase`.
- Super admin in tests is created by: `$u = User::factory()->create(); $u->super_admin = true; $u->save();` (matches `tests/Feature/Admin/UserManagementTest.php`).
- `super_admin` and the new `force_password_change` are NOT in the model's `#[Fillable]` list — set them directly on the model instance, never via mass assignment.
- Commit after each task with the shown message.

---

### Task 1: `force_password_change` column + model cast

**Files:**
- Create: `database/migrations/2026_06_18_110000_add_force_password_change_to_users_table.php`
- Modify: `app/Models/User.php:71-80` (the `casts()` method)
- Test: `tests/Feature/Admin/ForcePasswordChangeFlagTest.php`

**Interfaces:**
- Produces: `users.force_password_change` boolean column (default `false`); `User` casts `force_password_change` to `bool`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ForcePasswordChangeFlagTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_password_change_defaults_false_and_casts_to_bool(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->force_password_change);

        $user->force_password_change = 1;
        $user->save();

        $this->assertTrue($user->fresh()->force_password_change);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ForcePasswordChangeFlagTest`
Expected: FAIL — `force_password_change` column does not exist (SQL error / undefined column).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_18_110000_add_force_password_change_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('force_password_change')->default(false)->after('super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('force_password_change');
        });
    }
};
```

- [ ] **Step 4: Add the cast**

In `app/Models/User.php`, inside `casts()`, add the line after `'super_admin' => 'boolean',`:

```php
            'super_admin' => 'boolean',
            'force_password_change' => 'boolean',
```

- [ ] **Step 5: Migrate and run the test**

Run: `ddev php artisan migrate && ddev php artisan test --filter=ForcePasswordChangeFlagTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_18_110000_add_force_password_change_to_users_table.php app/Models/User.php tests/Feature/Admin/ForcePasswordChangeFlagTest.php
git commit -m "feat(users): add force_password_change column and cast"
```

---

### Task 2: Forced-change page (controller, request, routes, React)

This makes the change page reachable and functional. The gate that *forces* users onto it comes in Task 3.

**Files:**
- Create: `app/Http/Controllers/ForcePasswordChangeController.php`
- Create: `app/Http/Requests/ForcePasswordChangeRequest.php`
- Create: `resources/js/Pages/Auth/ForcePasswordChange.tsx`
- Modify: `routes/web.php:62-71` (the auth-only group)
- Test: `tests/Feature/Auth/ForcePasswordChangeTest.php`

**Interfaces:**
- Produces:
  - Route `app.password.change.show` → `GET /password/change` → renders Inertia `Auth/ForcePasswordChange`.
  - Route `app.password.change.update` → `PUT /password/change`, body `{current_password, password, password_confirmation}`. On success sets new password, sets `force_password_change = false`, redirects to `/` with flash `success`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/ForcePasswordChangeTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_page_renders_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.password.change.show'))
            ->assertOk();
    }

    public function test_updating_password_clears_the_flag(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->force_password_change = true;
        $user->save();

        $this->actingAs($user)
            ->put(route('app.password.change.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertFalse($user->force_password_change);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->force_password_change = true;
        $user->save();

        $this->actingAs($user)
            ->put(route('app.password.change.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->force_password_change);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ForcePasswordChangeTest`
Expected: FAIL — route `app.password.change.show` is not defined.

- [ ] **Step 3: Create the form request**

Create `app/Http/Requests/ForcePasswordChangeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForcePasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access enforced by the `auth` middleware
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/ForcePasswordChangeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForcePasswordChangeRequest;
use Illuminate\Http\Request;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request)
    {
        return inertia('Auth/ForcePasswordChange');
    }

    public function update(ForcePasswordChangeRequest $request)
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->force_password_change = false;
        $user->save();

        return redirect('/')->with('success', 'Password updated.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the auth-only group (`Route::middleware('auth')->group(...)`, after the logout route at line 63), add:

```php
        Route::get('/password/change', [\App\Http\Controllers\ForcePasswordChangeController::class, 'show'])->name('app.password.change.show');
        Route::put('/password/change', [\App\Http\Controllers\ForcePasswordChangeController::class, 'update'])->name('app.password.change.update');
```

- [ ] **Step 6: Run the backend test**

Run: `ddev php artisan test --filter=ForcePasswordChangeTest`
Expected: PASS (all three tests).

- [ ] **Step 7: Create the React page**

Create `resources/js/Pages/Auth/ForcePasswordChange.tsx`:

```tsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ForcePasswordChange() {
    const { data, setData, put, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('app.password.change.update'));
    };

    const inputClass =
        'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

    return (
        <GuestLayout
            title="Update your password"
            description="You must set a new password before continuing"
        >
            <Head title="Update password" />

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="current_password" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Current password
                    </label>
                    <input
                        id="current_password"
                        type="password"
                        autoComplete="current-password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        className={inputClass}
                        autoFocus
                    />
                    {errors.current_password && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.current_password}</p>}
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        New password
                    </label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        className={inputClass}
                    />
                    {errors.password && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.password}</p>}
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Confirm new password
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className={inputClass}
                    />
                    {errors.password_confirmation && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.password_confirmation}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
                >
                    {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                    Update password
                </button>
            </form>
        </GuestLayout>
    );
}
```

- [ ] **Step 8: Build the frontend**

Run: `ddev npm run build`
Expected: PASS (no TypeScript errors).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/ForcePasswordChangeController.php app/Http/Requests/ForcePasswordChangeRequest.php resources/js/Pages/Auth/ForcePasswordChange.tsx routes/web.php tests/Feature/Auth/ForcePasswordChangeTest.php
git commit -m "feat(auth): add forced password change page and endpoint"
```

---

### Task 3: Global force-change gate middleware

**Files:**
- Create: `app/Http/Middleware/ForcePasswordChange.php`
- Modify: `bootstrap/app.php:18-21` (the `$middleware->web(append: [...])` list)
- Test: `tests/Feature/Auth/ForcePasswordChangeGateTest.php`

**Interfaces:**
- Consumes: routes `app.password.change.show`, `app.password.change.update` (Task 2); `app.auth.logout`.
- Produces: a middleware in the `web` group that redirects any authenticated user with `force_password_change === true` to `app.password.change.show`, except on the two change routes and logout.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/ForcePasswordChangeGateTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeGateTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedUser(): User
    {
        $u = User::factory()->create();
        $u->force_password_change = true;
        $u->save();

        return $u;
    }

    public function test_flagged_user_is_redirected_to_change_page(): void
    {
        $this->actingAs($this->flaggedUser())
            ->get(route('app.profile.edit'))
            ->assertRedirect(route('app.password.change.show'));
    }

    public function test_flagged_user_can_reach_the_change_page_itself(): void
    {
        $this->actingAs($this->flaggedUser())
            ->get(route('app.password.change.show'))
            ->assertOk();
    }

    public function test_flagged_user_can_logout(): void
    {
        $this->actingAs($this->flaggedUser())
            ->post(route('app.auth.logout'))
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_unflagged_user_is_not_redirected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.profile.edit'))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ForcePasswordChangeGateTest`
Expected: FAIL — `test_flagged_user_is_redirected_to_change_page` gets 200 instead of a redirect (no gate yet).

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/ForcePasswordChange.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Routes a flagged user may still reach, so they are never trapped.
     */
    private const ALLOWED = [
        'app.password.change.show',
        'app.password.change.update',
        'app.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return redirect()->route('app.password.change.show');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware in the web group**

In `bootstrap/app.php`, add `ForcePasswordChange` to the `web(append: [...])` list and import it. The block becomes:

```php
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\ForcePasswordChange::class,
        ]);
```

(Adding the fully-qualified name avoids touching the `use` block; using a short import is equally fine.)

- [ ] **Step 5: Run the test**

Run: `ddev php artisan test --filter=ForcePasswordChangeGateTest`
Expected: PASS (all four tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/ForcePasswordChange.php bootstrap/app.php tests/Feature/Auth/ForcePasswordChangeGateTest.php
git commit -m "feat(auth): gate flagged users onto forced password change"
```

---

### Task 4: Admin can set the flag from the user form (backend)

**Files:**
- Modify: `app/Http/Requests/Admin/UserRequest.php:23-28` (rules array)
- Modify: `app/Http/Controllers/Admin/UserController.php` — `store()` (40-54), `edit()` (56-68), `update()` (70-89)
- Test: `tests/Feature/Admin/UserManagementTest.php` (add methods)

**Interfaces:**
- Consumes: `users.force_password_change` (Task 1).
- Produces: `UserRequest` validates `force_password_change` as boolean; `store`/`update` persist it; `edit` passes it to the Inertia page as `user.force_password_change`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/UserManagementTest.php` (inside the class):

```php
    public function test_update_sets_force_password_change_flag(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('app.admin.users.update', ['user' => $target->id]), [
                'name' => $target->name,
                'email' => $target->email,
                'force_password_change' => true,
            ])
            ->assertRedirect(route('app.admin.users.index'));

        $this->assertTrue($target->fresh()->force_password_change);
    }

    public function test_edit_page_exposes_force_password_change(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $target->force_password_change = true;
        $target->save();

        $this->actingAs($admin)
            ->get(route('app.admin.users.edit', ['user' => $target->id]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->where('user.force_password_change', true));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=UserManagementTest`
Expected: FAIL — `test_update_sets_force_password_change_flag` finds the flag still false; `test_edit_page_exposes_force_password_change` fails the `where` assertion (prop missing).

- [ ] **Step 3: Add validation rule**

In `app/Http/Requests/Admin/UserRequest.php`, add to the returned rules array after the `super_admin` line:

```php
            'super_admin' => ['boolean'],
            'force_password_change' => ['boolean'],
```

- [ ] **Step 4: Persist in `store()` and `update()`, expose in `edit()`**

In `app/Http/Controllers/Admin/UserController.php`:

In `store()`, after the `$user->super_admin = ...; $user->save();` block becomes:

```php
        $user->super_admin = (bool) ($data['super_admin'] ?? false);
        $user->force_password_change = (bool) ($data['force_password_change'] ?? false);
        $user->save();
```

In `edit()`, add the field to the passed `user` array:

```php
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'super_admin' => $model->super_admin,
                'force_password_change' => $model->force_password_change,
            ],
```

In `update()`, after `$model->super_admin = $wantsSuperAdmin;` and before `$model->save();`:

```php
        $model->super_admin = $wantsSuperAdmin;
        $model->force_password_change = (bool) ($data['force_password_change'] ?? false);
        $model->save();
```

- [ ] **Step 5: Run the test**

Run: `ddev php artisan test --filter=UserManagementTest`
Expected: PASS (existing tests plus the two new ones).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Admin/UserRequest.php app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(users): persist force_password_change from admin user form"
```

---

### Task 5: "Send Password Reset" admin action (backend)

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php` (add `sendPasswordReset()` + `use Illuminate\Support\Facades\Password;`)
- Modify: `routes/web.php:135-140` (admin users routes)
- Test: `tests/Feature/Admin/UserManagementTest.php` (add method)

**Interfaces:**
- Produces: route `app.admin.users.send-password-reset` → `POST /admin/users/{user}/send-password-reset`. Calls `Password::sendResetLink(['email' => $user->email])` and redirects back with a `success` or `error` flash.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/UserManagementTest.php`:

```php
    public function test_admin_can_send_password_reset_link(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.users.send-password-reset', ['user' => $target->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_send_password_reset_requires_super_admin(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.users.send-password-reset', ['user' => $target->id]))
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=UserManagementTest`
Expected: FAIL — route `app.admin.users.send-password-reset` not defined.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Admin/UserController.php`, add the import near the top:

```php
use Illuminate\Support\Facades\Password;
```

Add this method to the class (e.g. after `update()`):

```php
    public function sendPasswordReset(string $user)
    {
        $model = User::findOrFail($user);

        $status = Password::sendResetLink(['email' => $model->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Password reset link sent.');
        }

        return back()->with('error', __($status));
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, inside the admin group, after the user `destroy` route (line 140):

```php
        Route::post('/users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])->name('app.admin.users.send-password-reset');
```

- [ ] **Step 5: Run the test**

Run: `ddev php artisan test --filter=UserManagementTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php routes/web.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(users): admin send password reset link action"
```

---

### Task 6: User→project membership endpoints + edit() data

**Files:**
- Create: `app/Http/Controllers/Admin/UserProjectController.php`
- Modify: `routes/web.php` (admin group — add 3 routes + import)
- Modify: `app/Http/Controllers/Admin/UserController.php` — `edit()` to pass `memberships` and `availableProjects`
- Test: `tests/Feature/Admin/UserProjectMembershipTest.php`

**Interfaces:**
- Consumes: `User::projects()` belongsToMany with pivot `role` (cast `ProjectRole`) + `active`.
- Produces:
  - `app.admin.users.projects.store` → `POST /admin/users/{user}/projects`, body `{project_id, role}`.
  - `app.admin.users.projects.update` → `PATCH /admin/users/{user}/projects/{project}`, body `{role}`.
  - `app.admin.users.projects.destroy` → `DELETE /admin/users/{user}/projects/{project}`.
  - `edit()` Inertia props gain `memberships: {id,name,role}[]` and `availableProjects: {id,name}[]`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/UserProjectMembershipTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserProjectMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_admin_can_attach_user_to_project(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.users.projects.store', ['user' => $target->id]), [
                'project_id' => $project->id,
                'role' => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
            'role' => 'member',
            'active' => true,
        ]);
    }

    public function test_admin_can_change_membership_role(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($target->id, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->patch(route('app.admin.users.projects.update', ['user' => $target->id, 'project' => $project->id]), [
                'role' => 'admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_detach_user_from_project(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($target->id, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->delete(route('app.admin.users.projects.destroy', ['user' => $target->id, 'project' => $project->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_edit_page_exposes_memberships_and_available_projects(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $member = Project::factory()->create(['name' => 'Member Project']);
        $other = Project::factory()->create(['name' => 'Other Project']);
        $member->users()->attach($target->id, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.edit', ['user' => $target->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->has('memberships', 1)
                ->where('memberships.0.role', 'admin')
                ->has('availableProjects', 1)
                ->where('availableProjects.0.id', $other->id));
    }

    public function test_membership_routes_require_super_admin(): void
    {
        $target = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.users.projects.store', ['user' => $target->id]), [
                'project_id' => $project->id,
                'role' => 'member',
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=UserProjectMembershipTest`
Expected: FAIL — route `app.admin.users.projects.store` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/UserProjectController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserProjectController extends Controller
{
    public function store(Request $request, string $user)
    {
        $model = User::findOrFail($user);
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $model->projects()->syncWithoutDetaching([
            $data['project_id'] => ['role' => $data['role'], 'active' => true],
        ]);

        return back()->with('success', 'Project assigned.');
    }

    public function update(Request $request, string $user, string $project)
    {
        $model = User::findOrFail($user);
        $data = $request->validate(['role' => ['required', Rule::enum(ProjectRole::class)]]);

        $model->projects()->updateExistingPivot($project, ['role' => $data['role']]);

        return back()->with('success', 'Membership updated.');
    }

    public function destroy(string $user, string $project)
    {
        User::findOrFail($user)->projects()->detach($project);

        return back()->with('success', 'Project removed.');
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, add the import near the other admin controller imports (after line 5):

```php
use App\Http\Controllers\Admin\UserProjectController;
```

Inside the admin group, after the `send-password-reset` route from Task 5:

```php
        Route::post('/users/{user}/projects', [UserProjectController::class, 'store'])->name('app.admin.users.projects.store');
        Route::patch('/users/{user}/projects/{project}', [UserProjectController::class, 'update'])->name('app.admin.users.projects.update');
        Route::delete('/users/{user}/projects/{project}', [UserProjectController::class, 'destroy'])->name('app.admin.users.projects.destroy');
```

- [ ] **Step 5: Expose memberships + availableProjects in `edit()`**

In `app/Http/Controllers/Admin/UserController.php`, add the import:

```php
use App\Models\Project;
```

Replace the body of `edit()` so it loads memberships and the projects the user is not in:

```php
    public function edit(string $user)
    {
        $model = User::findOrFail($user);

        $memberships = $model->projects()
            ->orderBy('name')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'role' => $p->pivot->role->value,
            ]);

        $memberIds = $memberships->pluck('id');

        $availableProjects = Project::query()
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name]);

        return inertia('Admin/Users/Edit', [
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'super_admin' => $model->super_admin,
                'force_password_change' => $model->force_password_change,
            ],
            'memberships' => $memberships,
            'availableProjects' => $availableProjects,
        ]);
    }
```

Note: `$p->pivot->role` is a `ProjectRole` enum instance (the pivot casts it), so `->value` yields the `'admin'`/`'member'` string.

- [ ] **Step 6: Run the test**

Run: `ddev php artisan test --filter=UserProjectMembershipTest`
Expected: PASS (all five tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/UserProjectController.php app/Http/Controllers/Admin/UserController.php routes/web.php tests/Feature/Admin/UserProjectMembershipTest.php
git commit -m "feat(users): user-centric project membership endpoints"
```

---

### Task 7: Refactor the user edit page into three cards (frontend)

**Files:**
- Modify (rewrite): `resources/js/Pages/Admin/Users/Edit.tsx`
- Test: `ddev npm run build` (frontend gate)

**Interfaces:**
- Consumes: props `user: {id,name,email,super_admin,force_password_change}`, `memberships: {id,name,role}[]`, `availableProjects: {id,name}[]`; routes from Tasks 4–6.
- Produces: the finished UI. No new exported types beyond the local interfaces below.

- [ ] **Step 1: Rewrite the page**

Replace the entire contents of `resources/js/Pages/Admin/Users/Edit.tsx` with:

```tsx
import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { ProjectRole } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface EditUser {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
  force_password_change: boolean;
}

interface Membership {
  id: string;
  name: string;
  role: ProjectRole;
}

interface AvailableProject {
  id: string;
  name: string;
}

export default function AdminUsersEdit({
  user,
  memberships,
  availableProjects,
}: {
  user: EditUser;
  memberships: Membership[];
  availableProjects: AvailableProject[];
}) {
  const account = useForm({
    name: user.name,
    email: user.email,
    password: '',
    super_admin: user.super_admin,
    force_password_change: user.force_password_change,
  });
  const attach = useForm({ project_id: '', role: 'member' });

  const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  function saveAccount(e: React.FormEvent) {
    e.preventDefault();
    account.put(route('app.admin.users.update', { user: user.id }));
  }

  function sendPasswordReset() {
    router.post(route('app.admin.users.send-password-reset', { user: user.id }));
  }

  function attachProject(e: React.FormEvent) {
    e.preventDefault();
    attach.post(route('app.admin.users.projects.store', { user: user.id }), {
      onSuccess: () => attach.reset(),
    });
  }

  function changeRole(membership: Membership, role: string) {
    router.patch(route('app.admin.users.projects.update', { user: user.id, project: membership.id }), { role });
  }

  async function removeMembership(membership: Membership) {
    if (!(await confirmDelete({ title: 'Remove from project?', text: `Remove ${user.name} from ${membership.name}?`, confirmText: 'Remove' }))) return;
    router.delete(route('app.admin.users.projects.destroy', { user: user.id, project: membership.id }));
  }

  const cardClass = 'mb-8 max-w-2xl rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900';

  return (
    <AdminLayout title="Edit user">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Edit user</h1>

        {/* Account */}
        <form onSubmit={saveAccount} className={cardClass}>
          <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Account</h2>
          <div className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
              <input value={account.data.name} onChange={(e) => account.setData('name', e.target.value)} className={inputClass} />
              {account.errors.name && <p className="mt-1 text-xs text-red-600">{account.errors.name}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
              <input type="email" value={account.data.email} onChange={(e) => account.setData('email', e.target.value)} className={inputClass} />
              {account.errors.email && <p className="mt-1 text-xs text-red-600">{account.errors.email}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">New password (leave blank to keep)</label>
              <input type="password" value={account.data.password} onChange={(e) => account.setData('password', e.target.value)} className={inputClass} />
              {account.errors.password && <p className="mt-1 text-xs text-red-600">{account.errors.password}</p>}
            </div>
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={account.data.super_admin} onChange={(e) => account.setData('super_admin', e.target.checked)} />
              Super admin
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={account.data.force_password_change} onChange={(e) => account.setData('force_password_change', e.target.checked)} />
              Force password change on next login
            </label>
            <div className="flex gap-2">
              <button disabled={account.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Save</button>
              <Link href={route('app.admin.users.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Cancel</Link>
            </div>
          </div>
        </form>

        {/* Security actions */}
        <div className={cardClass}>
          <h2 className="mb-1 text-lg font-semibold text-slate-900 dark:text-white">Security actions</h2>
          <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">Email this user a link to reset their password.</p>
          <button onClick={sendPasswordReset} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Send password reset
          </button>
        </div>

        {/* Project memberships */}
        <div className={cardClass}>
          <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Project memberships</h2>

          {memberships.length === 0 ? (
            <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">Not a member of any project yet.</p>
          ) : (
            <div className="mb-4 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
              <table className="w-full text-sm">
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {memberships.map((m) => (
                    <tr key={m.id}>
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{m.name}</td>
                      <td className="px-4 py-3">
                        <select value={m.role} onChange={(e) => changeRole(m, e.target.value)} className="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                          <option value="admin">Admin</option>
                          <option value="member">Member</option>
                        </select>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <button onClick={() => removeMembership(m)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <form onSubmit={attachProject} className="flex items-end gap-2">
            <div className="flex-1">
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Add to project</label>
              <select value={attach.data.project_id} onChange={(e) => attach.setData('project_id', e.target.value)} className={inputClass}>
                <option value="">Select a project…</option>
                {availableProjects.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
              {attach.errors.project_id && <p className="mt-1 text-xs text-red-600">{attach.errors.project_id}</p>}
            </div>
            <select value={attach.data.role} onChange={(e) => attach.setData('role', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
            </select>
            <button disabled={attach.processing || !attach.data.project_id} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Add</button>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
```

- [ ] **Step 2: Build the frontend**

Run: `ddev npm run build`
Expected: PASS (TypeScript check clean, Vite build succeeds).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Users/Edit.tsx
git commit -m "feat(users): sectioned user edit page with projects and security actions"
```

---

### Task 8: Full verification pass

**Files:** none (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `ddev php artisan test`
Expected: PASS (all tests green, including the four new test classes/methods).

- [ ] **Step 2: Build the frontend**

Run: `ddev npm run build`
Expected: PASS.

- [ ] **Step 3: Manual smoke check (optional but recommended)**

As a super admin, open a user's edit page and confirm: Account card saves name/email/password/super_admin/force-change; "Send password reset" flashes success; adding/changing-role/removing a project works inline. Then set "force password change" on a test user, log in as them, and confirm every route redirects to `/password/change` until a new password is set, after which the dashboard loads.

---

## Self-Review Notes

- **Spec coverage:** Feature 1 (project attach) → Tasks 6–7. Feature 2 (send reset) → Tasks 5, 7. Feature 3 (force change) → Tasks 1–4, 7. Page refactor → Task 7. All spec sections map to tasks.
- **`force_password_change` in `store()`:** the spec calls for setting it at creation too; Task 4 adds it to `store()`. The Create page UI is out of scope for this plan (the spec only refactors the Edit page) — `store()` simply defaults it to `false` when the field is absent, which is safe.
- **Type consistency:** route names (`app.admin.users.projects.{store,update,destroy}`, `app.admin.users.send-password-reset`, `app.password.change.{show,update}`) and the `{user}`/`{project}` params are used identically across backend routes, tests, and the React page. Membership role is the string `'admin'|'member'` everywhere (pivot enum `->value` on the backend; `ProjectRole` TS type on the frontend).
