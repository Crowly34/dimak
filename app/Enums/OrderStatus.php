<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case PendingDiagnosis = 'pending_diagnosis';
    case InProgress = 'in_progress';
    case WaitingPart = 'waiting_part';
    case WaitingApproval = 'waiting_approval';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case NoRepair = 'no_repair';
    case Warranty = 'warranty';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingDiagnosis => 'Pending Diagnosis',
            self::InProgress => 'In Progress',
            self::WaitingPart => 'Waiting Part',
            self::WaitingApproval => 'Waiting Approval',
            self::Ready => 'Ready',
            self::Delivered => 'Delivered',
            self::NoRepair => 'No Repair',
            self::Warranty => 'Warranty',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingDiagnosis => 'gray',
            self::InProgress => 'info',
            self::WaitingPart => 'warning',
            self::WaitingApproval => 'warning',
            self::Ready => 'success',
            self::Delivered => 'success',
            self::NoRepair => 'danger',
            self::Warranty => 'primary',
        };
    }
}
