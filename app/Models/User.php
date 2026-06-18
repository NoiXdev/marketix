<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Pivot\ProjectUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user', 'user_id', 'project_id')
            ->withPivot('role', 'active')
            ->withTimestamps()
            ->using(ProjectUser::class);
    }

    public function roleInProject(Model $project): ?ProjectRole
    {
        return $this->projects()->whereKey($project->getKey())->first()?->pivot->role;
    }

    public function isProjectAdmin(Model $project): bool
    {
        return $this->super_admin || $this->roleInProject($project) === ProjectRole::Admin;
    }

    public function canAccessProject(Model $project): bool
    {
        if ($this->super_admin) {
            return true;
        }

        return $this->projects()->whereKey($project->getKey())->exists();
    }

    /**
     * Projects this user may access: every project for super admins,
     * otherwise only the ones they belong to. Returns a query so callers
     * can `->first()` or `->get([...])` without loading everything.
     */
    public function accessibleProjects(): Builder|BelongsToMany
    {
        return $this->super_admin
            ? Project::query()->orderBy('name')
            : $this->projects();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'super_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
