<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderLocation;
use App\Enums\OrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('folio')
                        ->required()
                        ->maxLength(20),
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required()->maxLength(120),
                            TextInput::make('phone')->maxLength(40),
                            Textarea::make('notes'),
                        ])
                        ->required(),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('device')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('device_serial')
                        ->label('Serial Number')
                        ->maxLength(80),
                ]),
                TextInput::make('device_password')
                    ->label('Device Password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null && $state !== '' ? encrypt($state) : null)
                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                        if ($state !== null && $state !== '') {
                            try {
                                $component->state(decrypt($state));
                            } catch (\Throwable) {
                                $component->state(null);
                            }
                        }
                    }),
                Grid::make(2)->schema([
                    DatePicker::make('received_at')
                        ->default(now()),
                    DatePicker::make('delivered_at'),
                ]),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('observations')
                    ->columnSpanFull(),
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(OrderStatus::class)
                        ->default(OrderStatus::PendingDiagnosis)
                        ->required(),
                    Select::make('location')
                        ->options(OrderLocation::class)
                        ->default(OrderLocation::Shop)
                        ->required(),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('price')
                        ->numeric()
                        ->prefix('$'),
                    Toggle::make('paid')
                        ->inline(false),
                ]),
            ]);
    }
}
