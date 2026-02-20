<?php

namespace App\Models;

use App\Enums\OrderLocation;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Order extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'folio',
        'client_id',
        'device',
        'device_serial',
        'device_password',
        'received_at',
        'description',
        'observations',
        'status',
        'location',
        'price',
        'paid',
        'delivered_at',
    ];

    protected array $auditExclude = ['device_password'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'location' => OrderLocation::class,
            'received_at' => 'date',
            'delivered_at' => 'date',
            'paid' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function getDecryptedDevicePasswordAttribute(): ?string
    {
        try {
            return decrypt($this->device_password);
        } catch (\Throwable) {
            return null;
        }
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}
