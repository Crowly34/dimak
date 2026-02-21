<?php

namespace App\Models;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
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
        'price',
        'paid',
    ];

    /** @var array<string> */
    protected array $auditExclude = ['device_password'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'location' => TicketLocation::class,
            'delivered_at' => 'date',
            'price' => 'decimal:2',
            'paid' => 'boolean',
        ];
    }

    public function getDecryptedDevicePasswordAttribute(): ?string
    {
        $password = $this->device_password;

        if (! is_string($password) || $password === '') {
            return null;
        }

        try {
            $decrypted = decrypt($password);

            return is_string($decrypted) ? $decrypted : null;
        } catch (\Throwable) {
            return null;
        }
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
