<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query',
        'parsed_client',
        'parsed_device',
        'parsed_status',
        'is_fallback',
        'order_results',
        'client_results',
        'duration_ms',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_fallback' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
