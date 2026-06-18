# User Edit Page Refactor — Design

Date: 2026-06-18
Branch: new-features

## Goal

Refactor the admin user edit page (`resources/js/Pages/Admin/Users/Edit.tsx`) from a flat
form into a sectioned page and add three capabilities:

1. A super admin can attach/detach a user to/from projects directly from the user edit page.
2. A "Send Password Reset" admin action that emails the user a standard reset link.
3. A "force password change on next login" flag, enforced app-wide via middleware.

All admin user routes already sit behind the `super_admin` middleware (the whole `/admin`
group). No new authorization layer is introduced.

## Current state (for reference)

- `Admin/Users/Edit.tsx` — flat form: name, email, optional password, `super_admin` checkbox.
  Submits `PUT app.admin.users.update`.
- `Admin/UserController` — `index/create/store/edit/update/destroy`. `update()` guards against
  an admin removing their own `super_admin`.
- `User` model — ULID PK; `super_admin` boolean cast; `projects()` belongsToMany via
  `project_user` pivot `withPivot('role','active')` using `Pivot\ProjectUser`. Two-factor
  columns already present.
- `project_user` pivot — `role` (cast to `ProjectRole` enum: Admin/Member) + `active` boolean.
- Project-centric membership UI already exists: `Admin/Projects/Edit.tsx` +
  `Admin/ProjectMemberController` (`store`/`update`/`destroy`) doing inline immediate actions.
- Password reset today is user-initiated only, via Laravel's `Password::sendResetLink()` /
  `Password::reset()` and the `password_reset_tokens` table.
- No `force_password_change` column exists.

## Page structure

The edit page becomes three cards:

1. **Account** — name, email, optional password, `super_admin`, and the new
   `force_password_change` checkbox. Persisted by the existing `PUT` update.
2. **Security actions** — "Send Password Reset" button (immediate action, separate endpoint,
   with a confirm step).
3. **Project memberships** — inline add / change-role / remove, mirroring
   `Admin/Projects/Edit.tsx`.

## Feature 1 — Project memberships (user-centric)

Decision: inline immediate actions (each add/change-role/remove fires its own request and
reloads via Inertia), consistent with the existing project-side pattern. Project changes are
independent of the Account form save.

New controller `Admin/UserProjectController` paralleling `Admin/ProjectMemberController`:

- `POST   /admin/users/{user}/projects` — body `{project_id, role}`; attach via
  `syncWithoutDetaching([$projectId => ['role' => $role, 'active' => true]])`.
- `PATCH  /admin/users/{user}/projects/{project}` — body `{role}`; update pivot role via
  `updateExistingPivot`.
- `DELETE /admin/users/{user}/projects/{project}` — detach.

Routes registered inside the existing `['auth','super_admin']` `/admin` group. Validation:
`project_id` exists in `projects`, `role` is a valid `ProjectRole` value.

`UserController@edit` additionally passes:
- `memberships` — the user's current projects with `{id, name, role}`.
- `availableProjects` — projects the user is **not** yet a member of, `{id, name}`, for the
  add dropdown.

UI in the Project memberships card:
- List of current memberships, each row: project name, role `<select>` (Admin/Member) that
  PATCHes on change, and a Remove button that DELETEs (with confirm).
- An "Add to project" row: project `<select>` (from `availableProjects`) + role `<select>`,
  and an Add button that POSTs.

## Feature 2 — Send Password Reset

- `POST /admin/users/{user}/send-password-reset` → new `UserController@sendPasswordReset` (or a
  small dedicated controller) calling `Password::sendResetLink(['email' => $user->email])`.
- Redirect back with a success flash on `Password::RESET_LINK_SENT`, otherwise an error flash.
- Uses the existing `password_reset_tokens` infrastructure and the standard public reset flow;
  no new tables. The user receives the standard reset email and sets a new password there.
- Button in the Security actions card, with a confirm step before sending.

## Feature 3 — Force password change on next login

### Data
- Migration: add `force_password_change` boolean (default `false`) to `users`.
- `User` model: add to `$fillable` and cast as `boolean`.

### Setting the flag
- Checkbox in the Account card, persisted by the existing `UserController@update`.
- Also add to `UserController@store` so it can be set when creating a user.
- Validation: add `force_password_change` (boolean) to `UserRequest`.

### Enforcing the flag (global gate)
- New middleware `ForcePasswordChange`, added to the authenticated app middleware group.
- If `auth()->user()?->force_password_change` is true, redirect **every** request to the
  dedicated `password.change` page and block all else — **except** the change-password
  route(s) themselves and logout, so the user is never trapped.

### Clearing the flag
- New `ForcedPasswordChangeController` with `show` and `update`.
- `show` renders a dedicated minimal change-password page (no app navigation to wander).
- `update` validates the new password (confirmed), sets the new hashed password, flips
  `force_password_change` to `false`, then redirects to the dashboard.
- Routes (auth-only, excluded from the force gate):
  - `GET  /password/change` → `show` (name `password.change`)
  - `PUT  /password/change` → `update`

### Edge cases
- An admin may set the flag on themselves; it simply routes them through the change page on
  their next request, which is harmless.
- The existing self-guard (an admin cannot strip their own `super_admin`) is unchanged.

## Testing

- Feature: super admin can attach a user to a project (role persisted, `active` true), change
  the role, and detach. Non-super-admin is blocked by `super_admin` middleware.
- Feature: "Send Password Reset" dispatches a reset link for the user's email and flashes
  success; a reset token row is created.
- Feature: setting `force_password_change` then hitting any app route redirects to
  `password.change`; the change page and logout remain reachable; submitting a valid new
  password clears the flag and lands on the dashboard.
- `npm run build` passes (TypeScript check) for the refactored page — used as the frontend gate
  since lint is broken.

## Out of scope

- Changing the project-centric membership UI in `Admin/Projects/Edit.tsx`.
- Reworking the public password reset flow or the invitation system.
- Any role model beyond the existing `super_admin` boolean and `ProjectRole` enum.
