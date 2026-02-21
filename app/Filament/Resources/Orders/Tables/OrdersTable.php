<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->sortable(),
                TextColumn::make('price')
                    ->money('MXN')
                    ->sortable(),
                IconColumn::make('paid')
                    ->boolean(),
                TextColumn::make('received_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('received_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Received from'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Received until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = is_string($data['from']) ? $data['from'] : null;
                        $until = is_string($data['until']) ? $data['until'] : null;

                        return $query
                            ->when($from, fn (Builder $q) => $q->whereDate('received_at', '>=', $from))
                            ->when($until, fn (Builder $q) => $q->whereDate('received_at', '<=', $until));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
