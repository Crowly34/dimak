<?php

namespace App\Models;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property TicketStatus $status
 * @property TicketLocation $location
 */
class Ticket extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'order_id',
        'device',
        'device_serial',
        'device_password',
        'delivered_at',
        'description',
        'observations',
        'status',
        'location',
        'sheet_row_hash',
    ];

    /** @var array<string> */
    protected array $auditExclude = ['device_password', 'sheet_row_hash'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'location' => TicketLocation::class,
            'delivered_at' => 'date',
        ];
    }

    /**
     * Encrypts on write and decrypts on read, so no call site can persist a
     * plaintext password by forgetting to encrypt. Reads fall back to null
     * rather than throwing, so one undecryptable row cannot break the panel.
     *
     * @return Attribute<?string, ?string>
     */
    protected function devicePassword(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?string {
                if (! is_string($value) || $value === '') {
                    return null;
                }

                try {
                    $decrypted = decrypt($value);
                } catch (\Throwable) {
                    return null;
                }

                return is_string($decrypted) ? $decrypted : null;
            },
            set: fn (mixed $value): ?string => is_string($value) && $value !== ''
                ? encrypt($value)
                : null,
        );
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<TicketStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class);
    }
}
