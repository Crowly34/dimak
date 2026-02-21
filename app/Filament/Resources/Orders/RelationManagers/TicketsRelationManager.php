<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        if (is_string($state) && $state !== '') {
                            try {
                                $component->state(decrypt($state));
                            } catch (\Throwable) {
                                $component->state(null);
                            }
                        }
                    }),
                Grid::make(2)->schema([
                    DatePicker::make('delivered_at'),
                    DatePicker::make('received_at')
                        ->hidden(),
                ]),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('observations')
                    ->columnSpanFull(),
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(TicketStatus::class)
                        ->default(TicketStatus::PendingDiagnosis)
                        ->required(),
                    Select::make('location')
                        ->options(TicketLocation::class)
                        ->default(TicketLocation::Shop)
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('device')
            ->columns([
                TextColumn::make('device')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('location')
                    ->badge()
                    ->searchable(),
                TextColumn::make('price')
                    ->money('MXN'),
                IconColumn::make('paid')
                    ->boolean(),
                TextColumn::make('delivered_at')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('status')
                            ->label('New Status')
                            ->options(TicketStatus::class)
                            ->required(),
                        Textarea::make('note')
                            ->label('Note (optional)'),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        TicketStatusLog::create([
                            'ticket_id' => $record->id,
                            'from_status' => $record->status->value,
                            'to_status' => $data['status'],
                            'note' => $data['note'] ?? null,
                        ]);

                        $record->withoutEvents(fn () => $record->update(['status' => $data['status']]));
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
