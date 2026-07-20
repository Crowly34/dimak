<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * @deprecated Use sheets:sync (SyncOrdersFromSheet action) for Google Sheets sync.
 *             Kept as fallback for one-off CSV imports.
 */
class ImportOrders extends Command
{
    protected $signature = 'import:orders
                            {file : Path to the CSV file}
                            {--dry-run : Preview what would be imported without writing to the database}';

    protected $description = 'Import Mac repair orders from a CSV file';

    private int $imported = 0;

    private int $skipped = 0;

    private int $clientsCreated = 0;

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            $this->error("Could not open file: {$file}");

            return self::FAILURE;
        }

        $rowIndex = 0;
        $syntheticCounter = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;

            // Skip first 4 rows (filter row, headers, blanks)
            if ($rowIndex <= 4) {
                continue;
            }

            $folio = trim($row[0] ?? '');
            $device = trim($row[1] ?? '');

            // Skip rows with both folio and device empty
            if ($folio === '' && $device === '') {
                continue;
            }

            $folio = $this->normalizeFolio($folio);

            if ($folio === '') {
                $folio = 'IMP-'.$syntheticCounter++;
            }

            $clientName = trim($row[2] ?? '');
            $clientPhone = trim($row[3] ?? '');
            $receivedAt = trim($row[4] ?? '');
            $description = trim($row[5] ?? '');
            $deviceSerial = trim($row[6] ?? '');
            $devicePassword = trim($row[7] ?? '');
            $observations = trim($row[8] ?? '');

            // Skip duplicate folios
            if (Order::where('folio', $folio)->exists()) {
                $this->warn("Skipping duplicate folio: {$folio}");
                $this->skipped++;

                continue;
            }

            [$status, $location, $deliveredAt] = $this->regexInfer($observations);

            $parsedReceivedAt = $this->parseDate($receivedAt);
            $parsedDeliveredAt = $deliveredAt;

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '[DRY-RUN] Folio: %s | Client: %s | Device: %s | Status: %s | Location: %s',
                    $folio,
                    $clientName ?: '(none)',
                    $device ?: '(none)',
                    $status,
                    $location
                ));
                $this->imported++;

                continue;
            }

            $client = $this->findOrCreateClient($clientName, $clientPhone);

            $order = Order::create([
                'folio' => $folio,
                'client_id' => $client->id,
                'received_at' => $parsedReceivedAt,
            ]);

            Ticket::create([
                'order_id' => $order->id,
                'device' => $device ?: 'Unknown',
                'device_serial' => $deviceSerial ?: null,
                'device_password' => $devicePassword !== '' ? encrypt($devicePassword) : null,
                'description' => $description ?: null,
                'observations' => $observations ?: null,
                'status' => $status,
                'location' => $location,
                'delivered_at' => $parsedDeliveredAt,
            ]);

            $this->imported++;
        }

        fclose($handle);

        $this->info(sprintf(
            '%s Done. Imported: %d | Skipped: %d | Clients created: %d',
            $this->option('dry-run') ? '[DRY-RUN]' : '',
            $this->imported,
            $this->skipped,
            $this->clientsCreated
        ));

        return self::SUCCESS;
    }

    private function normalizeFolio(string $folio): string
    {
        $stripped = str_replace('.', '', $folio);

        if ($stripped !== '' && ctype_digit($stripped)) {
            return $stripped;
        }

        return $folio;
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            // Try d/m/Y format
            $parts = explode('/', $value);

            if (count($parts) === 3) {
                $timestamp = mktime(0, 0, 0, (int) $parts[1], (int) $parts[0], (int) $parts[2]);
            }
        }

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * @return array{0: string, 1: string, 2: null}
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

        return [$status, $location, null];
    }

    private function findOrCreateClient(string $name, string $rawPhone): Client
    {
        $phones = array_filter(
            array_map('trim', preg_split('/[\/,]/', $rawPhone) ?: []),
            fn (string $p) => $p !== ''
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

        $this->clientsCreated++;

        return Client::create([
            'name' => $name ?: 'Unknown',
            'phone' => $rawPhone ?: null,
        ]);
    }
}
