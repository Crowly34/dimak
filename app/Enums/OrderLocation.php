<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderLocation: string implements HasColor, HasLabel
{
    case Shop = 'shop';
    case Lab = 'lab';
    case Client = 'client';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Shop => 'Shop',
            self::Lab => 'Lab',
            self::Client => 'Client',
            self::Delivered => 'Delivered',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Shop => 'primary',
            self::Lab => 'info',
            self::Client => 'warning',
            self::Delivered => 'success',
        };
    }
}
