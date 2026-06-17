<?php

namespace App\Models;

use App\Observers\DomainObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([DomainObserver::class])]
class Domain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'redirect_root',
        'redirect_not_found',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
