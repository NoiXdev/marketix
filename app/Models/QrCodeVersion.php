<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'version',
        'name',
        'type',
        'is_dynamic',
        'content',
        'style',
        'domain_id',
        'slug',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'content' => 'array',
            'style' => 'array',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
