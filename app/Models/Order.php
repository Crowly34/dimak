<?php

namespace App\Models;

use App\Enums\OrderLocation;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

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

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (Order $order): void {
            if ($order->isDirty('status')) {
                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'from_status' => $order->getOriginal('status'),
                    'to_status' => $order->status instanceof OrderStatus
                        ? $order->status->value
                        : $order->status,
                ]);
            }
        });
    }

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
