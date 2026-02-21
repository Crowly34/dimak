<?php

namespace App\Filament\Imports;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class OrderImporter extends Importer
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('folio')
                ->requiredMapping()
                ->rules(['required', 'max:20'])
                ->guess(['folio', 'Folio', 'order_number', 'numero'])
                ->castStateUsing(fn (?string $state): ?string => self::normalizeFolioValue($state ?? ''))
                ->example('1234'),

            ImportColumn::make('device')
                ->label('Device')
                ->requiredMapping()
                ->rules(['required'])
                ->guess(['device', 'dispositivo', 'Device', 'Dispositivo'])
                ->example('MacBook Pro 13"'),

            ImportColumn::make('client_name')
                ->label('Client Name')
                ->guess(['client_name', 'client', 'cliente', 'Cliente', 'nombre'])
                ->example('John Doe'),

            ImportColumn::make('phone')
                ->label('Phone')
                ->guess(['phone', 'telefono', 'teléfono', 'Teléfono'])
                ->example('555-1234'),

            ImportColumn::make('received_at')
                ->label('Received At')
                ->guess(['received_at', 'received', 'fecha', 'Fecha'])
                ->castStateUsing(fn (?string $state): ?string => self::parseDateValue($state ?? ''))
                ->example('2024-01-15'),

            ImportColumn::make('description')
                ->label('Description')
                ->guess(['description', 'descripcion', 'descripción', 'Descripción'])
                ->example('No enciende'),

            ImportColumn::make('device_serial')
                ->label('Device Serial')
                ->guess(['device_serial', 'serial', 'serie', 'Serie'])
                ->example('C02XYZ12345'),

            ImportColumn::make('device_password')
                ->label('Device Password')
                ->guess(['device_password', 'password', 'contraseña', 'Contraseña'])
                ->example('1234'),

            ImportColumn::make('observations')
                ->label('Observations')
                ->guess(['observations', 'observaciones', 'Observaciones', 'notes', 'notas'])
                ->example('En proceso de reparación'),
        ];
    }

    public function resolveRecord(): ?Order
    {
        $folio = $this->data['folio'] ?? '';

        if ($folio === '' || $folio === null) {
            return null;
        }

        if (Order::where('folio', $folio)->exists()) {
            return null;
        }

        $client = $this->findOrCreateClient(
            $this->data['client_name'] ?? '',
            $this->data['phone'] ?? '',
        );

        $order = new Order;
        $order->client_id = $client->id;

        return $order;
    }

    protected function afterSave(): void
    {
        [$status, $location] = $this->inferStatusAndLocation($this->data['observations'] ?? '');
        $password = $this->data['device_password'] ?? '';

        Ticket::create([
            'order_id' => $this->record->id,
            'device' => ($this->data['device'] ?? '') ?: 'Unknown',
            'device_serial' => ($this->data['device_serial'] ?? '') ?: null,
            'device_password' => $password !== '' ? encrypt($password) : null,
            'description' => ($this->data['description'] ?? '') ?: null,
            'observations' => ($this->data['observations'] ?? '') ?: null,
            'status' => $status,
            'location' => $location,
            'delivered_at' => null,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your order import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    private static function normalizeFolioValue(string $folio): ?string
    {
        if ($folio === '') {
            return null;
        }

        $stripped = str_replace('.', '', $folio);

        if ($stripped !== '' && ctype_digit($stripped)) {
            return $stripped;
        }

        return $folio;
    }

    private static function parseDateValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            $parts = explode('/', $value);

            if (count($parts) === 3) {
                $timestamp = mktime(0, 0, 0, (int) $parts[1], (int) $parts[0], (int) $parts[2]);
            }
        }

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * @return array{0: TicketStatus, 1: TicketLocation}
     */
    private function inferStatusAndLocation(string $text): array
    {
        $lower = mb_strtolower($text);

        $status = TicketStatus::PendingDiagnosis;
        $location = TicketLocation::Shop;

        if (preg_match('/entregad|lista\b|listo\b/u', $lower)) {
            $status = TicketStatus::Delivered;
            $location = TicketLocation::Delivered;
        } elseif (preg_match('/no se pudo|sin reparaci[oó]n|no tiene arreglo/u', $lower)) {
            $status = TicketStatus::NoRepair;
        } elseif (preg_match('/esperando|buscando|en espera de pieza|sin refacci[oó]n/u', $lower)) {
            $status = TicketStatus::WaitingPart;
        } elseif (preg_match('/aprobaci[oó]n|autorizar|esperando respuesta/u', $lower)) {
            $status = TicketStatus::WaitingApproval;
        } elseif (preg_match('/en proceso|trabajando|reparando/u', $lower)) {
            $status = TicketStatus::InProgress;
        } elseif (preg_match('/lista para recoger|listo para entregar/u', $lower)) {
            $status = TicketStatus::Ready;
        }

        if (preg_match('/laboratorio|en lab\b/u', $lower)) {
            $location = TicketLocation::Lab;
        } elseif (preg_match('/con el cliente|en casa del|enviado a/u', $lower)) {
            $location = TicketLocation::Client;
        }

        return [$status, $location];
    }

    private function findOrCreateClient(string $name, string $rawPhone): Client
    {
        $phones = array_filter(
            array_map('trim', preg_split('/[\/,]/', $rawPhone) ?: []),
            fn (string $p) => $p !== '',
        );

        foreach ($phones as $phone) {
            $client = Client::where('phone', 'like', '%'.$phone.'%')->first();

            if ($client) {
                return $client;
            }
        }

        if ($name !== '') {
            $client = Client::where('name', $name)->first();

            if ($client) {
                return $client;
            }
        }

        return Client::create([
            'name' => $name ?: 'Unknown',
            'phone' => $rawPhone ?: null,
        ]);
    }
}
