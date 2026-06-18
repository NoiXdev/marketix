<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectInvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInvitation extends Model
{
    /** @use HasFactory<ProjectInvitationFactory> */
    use HasFactory, HasUlids;

    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $fillable = [
        'project_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function canResend(): bool
    {
        if ($this->accepted_at !== null) {
            return false;
        }

        return $this->last_sent_at === null
            || $this->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isPast();
    }

    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
