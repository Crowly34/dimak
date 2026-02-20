<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderLocation;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('folio')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('client.name')
                    ->searchable(),
                TextColumn::make('device')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('location')
                    ->badge()
                    ->searchable(),
                TextColumn::make('received_at')
                    ->date()
                    ->sortable(),
                IconColumn::make('paid')
                    ->boolean(),
                TextColumn::make('days_open')
                    ->label('Days Open')
                    ->state(function (Order $record): ?int {
                        if (in_array($record->status, [OrderStatus::Delivered, OrderStatus::NoRepair])) {
                            return null;
                        }

                        return $record->received_at
                            ? (int) $record->received_at->diffInDays(now())
                            : null;
                    })
                    ->sortable(false),
                TextColumn::make('price')
                    ->money('MXN')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('location')
                    ->options(OrderLocation::class),
                TernaryFilter::make('paid'),
                Filter::make('received_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Received from'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Received until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q) => $q->whereDate('received_at', '>=', $data['from']))
                            ->when($data['until'], fn (Builder $q) => $q->whereDate('received_at', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('status')
                            ->label('New Status')
                            ->options(OrderStatus::class)
                            ->required(),
                        Textarea::make('note')
                            ->label('Note (optional)'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $fromStatus = $record->status instanceof OrderStatus
                            ? $record->status->value
                            : $record->status;

                        OrderStatusLog::create([
                            'order_id' => $record->id,
                            'from_status' => $fromStatus,
                            'to_status' => $data['status'],
                            'note' => $data['note'] ?? null,
                        ]);

                        // Update without triggering the boot observer (log already created)
                        $record->withoutEvents(fn () => $record->update(['status' => $data['status']]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-banknotes')
                        ->action(fn (Collection $records) => $records->each->update(['paid' => true]))
                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
