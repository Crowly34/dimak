<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(40),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
