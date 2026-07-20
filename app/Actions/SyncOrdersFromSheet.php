<?php

namespace App\Actions;

use App\DTOs\SheetRow;
use App\DTOs\SyncResult;
use App\Integrations\GoogleSheets\GoogleSheetsClient;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOrdersFromSheet
{
    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $syntheticCounter = 1;

    public function __construct(
        private GoogleSheetsClient $client,
    ) {}

    public function __invoke(): SyncResult
    {
        $rows = $this->client->fetchRows();

        /** @var array<int|string, string|null> $existingHashes */
        $existingHashes = Ticket::query()
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->pluck('tickets.sheet_row_hash', 'orders.folio')
            ->toArray();

        $rows->chunk(100)->each(function ($chunk) use ($existingHashes): void {
            DB::transaction(function () use ($chunk, $existingHashes): void {
                foreach ($chunk as $index => $row) {
                    try {
                        $this->processRow($row, $existingHashes);
                    } catch (\Throwable $e) {
                        Log::warning('Sheet sync: row skipped', [
                            'folio' => $row->folio,
                            'index' => $index,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });

        cache()->put('sheets:last_synced_at', now());

        return new SyncResult($this->created, $this->updated, $this->skipped);
    }

    /**
     * @param  array<int|string, string|null>  $existingHashes
     */
    private function processRow(SheetRow $row, array $existingHashes): void
    {
        if ($row->isEmpty()) {
            return;
        }

        $folio = $row->folio !== '' ? $row->folio : 'IMP-'.$this->syntheticCounter++;

        if (isset($existingHashes[$folio]) && $existingHashes[$folio] === $row->hash) {
            $this->skipped++;

            return;
        }

        [$status, $location] = $this->regexInfer($row->observations);
        $client = $this->findOrCreateClient($row->clientName, $row->clientPhone);

        $existingOrder = Order::where('folio', $folio)->first();

        if ($existingOrder) {
            $this->updateExisting($existingOrder, $client, $row, $status, $location);
        } else {
            $this->createNew($folio, $client, $row, $status, $location);
        }
    }

    private function createNew(string $folio, Client $client, SheetRow $row, string $status, string $location): void
    {
        $order = Order::create([
            'folio' => $folio,
            'client_id' => $client->id,
            'received_at' => $row->receivedAt,
        ]);

        Ticket::create([
            'order_id' => $order->id,
            'device' => $row->device ?: 'Unknown',
            'device_serial' => $row->deviceSerial ?: null,
            'device_password' => $row->devicePassword !== '' ? encrypt($row->devicePassword) : null,
            'description' => $row->description ?: null,
            'observations' => $row->observations ?: null,
            'status' => $status,
            'location' => $location,
            'sheet_row_hash' => $row->hash,
        ]);

        $this->created++;
    }

    private function updateExisting(Order $order, Client $client, SheetRow $row, string $status, string $location): void
    {
        $order->update([
            'client_id' => $client->id,
            'received_at' => $row->receivedAt,
        ]);

        $ticket = $order->tickets()->first();

        if (! $ticket) {
            return;
        }

        $ticket->update([
            'device' => $row->device ?: 'Unknown',
            'device_serial' => $row->deviceSerial ?: null,
            'device_password' => $row->devicePassword !== '' ? encrypt($row->devicePassword) : null,
            'description' => $row->description ?: null,
            'observations' => $row->observations ?: null,
            'status' => $status,
            'location' => $location,
            'sheet_row_hash' => $row->hash,
        ]);

        $this->updated++;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function regexInfer(string $text): array
    {
        $lower = mb_strtolower($text);

        $status = 'pending_diagnosis';
        $location = 'shop';

        if (preg_match('/entregad|lista\b|listo\b/u', $lower)) {
            $status = 'delivered';
            $location = 'delivered';
        } elseif (preg_match('/no se pudo|sin reparaci[oó]n|no tiene arreglo/u', $lower)) {
            $status = 'no_repair';
        } elseif (preg_match('/esperando|buscando|en espera de pieza|sin refacci[oó]n/u', $lower)) {
            $status = 'waiting_part';
        } elseif (preg_match('/aprobaci[oó]n|autorizar|esperando respuesta/u', $lower)) {
            $status = 'waiting_approval';
        } elseif (preg_match('/en proceso|trabajando|reparando/u', $lower)) {
            $status = 'in_progress';
        } elseif (preg_match('/lista para recoger|listo para entregar/u', $lower)) {
            $status = 'ready';
        }

        if (preg_match('/laboratorio|en lab\b/u', $lower)) {
            $location = 'lab';
        } elseif (preg_match('/con el cliente|en casa del|enviado a/u', $lower)) {
            $location = 'client';
        }

        return [$status, $location];
    }

    private function findOrCreateClient(string $name, string $rawPhone): Client
    {
        $phones = array_filter(
            array_map('trim', preg_split('/[\/,]/', $rawPhone) ?: []),
            fn (string $p): bool => $p !== ''
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
