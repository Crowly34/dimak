# Google Sheets Sync

## Overview

One-way sync from the "Servicio Activo" Google Sheet into the application database. The Google Sheet is the **source of truth** — all synced fields are overwritten on each sync, including status/location (re-inferred from observations text). Serves as a transition tool so shop owners can keep using the spreadsheet they're familiar with while the Filament app stays up to date.

If the shop fully adopts the Filament app, this sync becomes unnecessary and can be removed. If they keep using both, bidirectional sync could be explored — but that's a separate decision for the future.

## Requirements

- Read order data from a Google Sheet via service account authentication
- Create new clients and orders/tickets for rows not yet in the database
- Update existing orders/tickets when sheet data has changed (sheet always wins)
- Detect changes efficiently via hash fingerprint — skip unchanged rows
- Provide a manual "Sync Now" button in the Filament admin panel
- Run automatically once daily via Laravel scheduler
- Display when the last sync occurred
- Rate-limit the manual sync button (30 seconds cooldown — prevents accidental double/triple clicks)
- No AI inference — use heuristic (regex) parsing for status/location
- No delete handling — rows removed from the sheet are not deleted from the DB
- One-way only: sheet → DB

## Scope

### In scope

- Google Sheets API read access via service account
- `SheetRow` DTO for typed row data between integration and action
- `SyncResult` DTO for action return values
- Hash fingerprint change detection (`sheet_row_hash` column on tickets)
- Row parsing: folio normalization, date parsing, status/location inference, password encryption
- Client deduplication (phone match → name match → create)
- Create or update orders and tickets by folio
- Chunked transactions (100 rows per batch)
- Artisan command: `sheets:sync`
- Filament dashboard action with last-synced display and rate limiting
- Daily scheduled execution
- Tests for integration, action, and command
- Developer docs in `docs/` (architecture, gotchas, how to extend)

### Out of scope

- Writing back to the sheet (bidirectional sync)
- AI-based status inference
- Deleting DB records when rows are removed from the sheet
- Sync conflict resolution
- Sync history/log table (use cache for last-synced timestamp)

## Acceptance Criteria

### Change Detection

1. **Hash fingerprint**: Each ticket stores a `sheet_row_hash` (md5 of the raw row data). On sync, the action bulk-loads all existing hashes in one query, computes the hash for each sheet row, and only processes rows where the hash differs or doesn't exist.
2. **Unchanged rows**: When a row's computed hash matches the stored hash, skip it entirely — no DB writes, no field comparison.

### Sync Action

3. **New order**: When a row exists in the sheet with a folio not in the database, create a Client (or match existing), an Order, and a Ticket with all parsed fields. Store the computed hash.
4. **Existing order — changed**: When a row's hash differs from the stored hash, update all synced fields on the ticket and order. Re-encrypt password. Re-infer status/location from observations. Update the stored hash.
5. **Sheet always wins**: All synced fields are overwritten on update, including status and location (re-inferred from observations). Filament-only edits to synced fields will be overwritten on next sync.
6. **Empty rows**: Rows where both folio and device are empty are skipped.
7. **Folio normalization**: `1.598` → `1598` (strip period if result is all digits); `3600Ñ` stays as-is.
8. **Missing folio**: Rows with no folio get a synthetic folio `IMP-{n}`.
9. **Client dedup**: Match by phone first (each segment split on `/,`), then by name, then create.
10. **Status/location inference**: Uses regex heuristic on observations text (same rules as CSV import).
11. **Password encryption**: Non-empty passwords are stored via `encrypt()`.
12. **Date parsing**: Handles `d/m/Y` format, falls back to `strtotime()`, null on failure.

### Manual Sync (Filament)

13. A "Sync from Sheet" action is available on the Filament dashboard or order list page.
14. The action displays the last sync time (e.g., "Last sync: 2 hours ago").
15. The action is disabled if the last sync was less than 30 seconds ago (anti-double-click).
16. On completion, a notification shows a summary: "Synced: 3 new, 2 updated, 980 unchanged".

### Scheduled Sync

17. The sync runs automatically once daily via Laravel's task scheduler (`routes/console.php`).

### Error Handling

18. **API-level errors** (unreachable, auth failure, quota): The sync aborts before any DB writes, logs the error via Laravel's default log channel (`storage/logs/laravel.log`), and the command returns `FAILURE`. The Filament action shows an error notification.
19. **Row-level errors** (unparseable data): The individual row is skipped, a warning is logged with the row index and folio. Other rows in the same chunk continue. The chunk's transaction commits successfully for all non-errored rows.
20. **Chunked transactions**: Rows are processed in batches of 100 within individual DB transactions. If a chunk fails entirely, only that chunk rolls back — previous chunks are already committed.
21. **Future consideration**: If error visibility becomes a problem, add Flare/Sentry integration or a failed-syncs dashboard widget. For now, log files are sufficient since this runs locally.

## Tests

### Unit Tests

```
tests/Unit/DTOs/SheetRowTest.php
```

- `it creates a SheetRow from a raw array`
- `it handles missing columns gracefully`
- `it normalizes folio by stripping periods from numeric folios`
- `it preserves non-numeric folios as-is`
- `it computes a consistent hash for the same row data`
- `it computes different hashes when row data changes`
- `it parses d/m/Y date format`
- `it returns null for unparseable dates`

```
tests/Unit/Actions/SyncOrdersFromSheetTest.php
```

- `it creates a new order and ticket from a SheetRow`
- `it stores the sheet_row_hash on created tickets`
- `it creates a new client when no match exists`
- `it matches an existing client by phone`
- `it matches an existing client by name when phone differs`
- `it updates a ticket when hash differs`
- `it re-encrypts password when sheet password changes`
- `it re-infers status and location on update`
- `it skips rows where hash matches (unchanged)`
- `it skips rows where folio and device are both empty`
- `it assigns synthetic folio when folio is empty`
- `it infers delivered status from observations text`
- `it infers no_repair status from observations text`
- `it infers waiting_part status from observations text`
- `it defaults to pending_diagnosis when no pattern matches`

### Feature Tests

```
tests/Feature/SyncOrdersFromSheetTest.php
```

- `it syncs new orders from google sheets` — mock integration, run action, assert DB records.
- `it updates existing orders when sheet data changes`
- `it skips unchanged orders based on hash`
- `it handles mixed new, changed, and unchanged orders in one sync`
- `it processes rows in chunked transactions`

```
tests/Feature/Commands/SheetsSyncCommandTest.php
```

- `it runs the sync and outputs summary`
- `it handles API errors gracefully and returns failure`

```
tests/Feature/Filament/SheetsSyncActionTest.php
```

- `it shows the sync action on the dashboard`
- `it executes the sync and shows notification`
- `it is rate limited to 30 seconds`
- `it displays the last sync timestamp`

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     Entry Points                        │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │ sheets:sync   │  │ Filament     │  │ Scheduler     │  │
│  │ (Command)     │  │ Action       │  │ (daily)       │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬────────┘  │
│         │                 │                 │           │
│         └────────────┬────┘─────────────────┘           │
│                      ▼                                  │
│         ┌────────────────────────┐                      │
│         │ SyncOrdersFromSheet    │                      │
│         │ (Action)               │                      │
│         │                        │                      │
│         │ - Bulk-loads hashes    │                      │
│         │ - Skips unchanged     │                      │
│         │ - Creates/updates in   │                      │
│         │   chunks of 100        │                      │
│         │ - Returns SyncResult   │                      │
│         └───────────┬────────────┘                      │
│                     │                                   │
│         ┌───────────▼────────────┐                      │
│         │ GoogleSheetsClient     │                      │
│         │ (Integration)          │                      │
│         │                        │                      │
│         │ - Authenticates via    │                      │
│         │   service account      │                      │
│         │ - Fetches range        │                      │
│         │ - Returns SheetRow[]   │                      │
│         └───────────┬────────────┘                      │
│                     │                                   │
└─────────────────────┼───────────────────────────────────┘
                      ▼
            Google Sheets API v4
```

### Data Flow

```
Sheet row (raw array)
    │
    ▼
SheetRow::fromArray()          ← normalize folio, parse date, trim fields
    │
    ▼
SheetRow::hash()               ← md5 of raw row for change detection
    │
    ▼
Compare hash to DB             ← bulk SELECT folio, sheet_row_hash FROM tickets
    │
    ├─ hash matches → skip
    ├─ no match     → create (client + order + ticket)
    └─ hash differs → update (all synced fields, re-infer status)
```

### File Structure

```
app/
├── Actions/
│   └── SyncOrdersFromSheet.php           # Business logic: hash check, dedup, upsert
├── DTOs/
│   ├── SheetRow.php                      # Typed row + hash computation
│   └── SyncResult.php                    # Sync outcome counts
├── Integrations/
│   └── GoogleSheets/
│       └── GoogleSheetsClient.php        # API isolation: fetch & map rows to DTOs
├── Console/Commands/
│   ├── SyncFromSheet.php                 # Artisan command (thin)
│   └── ImportOrders.php                  # CSV import (deprecated — kept as fallback)

docs/
└── google-sheets-sync.md                 # Developer docs: architecture, gotchas, extending

routes/
└── console.php                           # Schedule: ->daily()

config/
└── google.php                            # Already exists

database/migrations/
└── xxxx_add_sheet_row_hash_to_tickets.php  # New column for hash fingerprint
```

### Key Design Decisions

1. **Hash fingerprint for change detection** — each ticket stores `sheet_row_hash` (md5 of the raw row joined by `|`). On sync, one `SELECT folio, sheet_row_hash FROM tickets` loads all hashes. Per-row comparison is a string match in PHP — no field-by-field diffing, no password decryption needed. ~95% of rows will be skipped on a typical sync.

2. **Sheet always wins** — all synced fields are overwritten, including status and location (re-inferred from observations). This is intentional: the sheet is the source of truth during the transition period. If users start relying on Filament-only status changes, that signals they've transitioned and the sync can be turned off.

3. **Chunked transactions (100 rows/batch)** — balances atomicity and resilience. A bad row in chunk 7 doesn't roll back chunks 1-6. Row-level errors within a chunk are caught, logged, and skipped — the chunk still commits for valid rows.

4. **Integration pattern** (`app/Integrations/GoogleSheets/`) — isolates the external API dependency. Tests mock the client, not the Sheets facade. If the package changes, only the client changes.

5. **DTOs for data boundaries** — `SheetRow` encapsulates a parsed spreadsheet row with typed properties and hash computation. `SyncResult` wraps the outcome counts. Both are simple readonly classes.

6. **Action pattern** for sync logic — a single invokable class callable from command, Filament action, and scheduler. Returns `SyncResult`.

7. **Cache for last-synced** — `cache()->put('sheets:last_synced_at', now())` after successful sync. No migration needed. Rate limiting checks this same key.

8. **ImportOrders deprecated** — the CSV import command is kept as a fallback but marked `@deprecated`. The shared parsing logic (folio normalization, date parsing, regex inference, client dedup) lives in the Action and DTOs.

9. **Error handling via log channel** — errors go to Laravel's default log (`storage/logs/laravel.log`). No separate sync log table. Flare/Sentry can be added later if needed.

## Sample Code

### SheetRow DTO

```php
<?php

namespace App\DTOs;

readonly class SheetRow
{
    public function __construct(
        public string $folio,
        public string $device,
        public string $clientName,
        public string $clientPhone,
        public ?string $receivedAt,
        public string $description,
        public string $deviceSerial,
        public string $devicePassword,
        public string $observations,
        public string $hash,
    ) {}

    public static function fromArray(array $row): self
    {
        $folio = self::normalizeFolio(trim($row[0] ?? ''));

        return new self(
            folio: $folio,
            device: trim($row[1] ?? ''),
            clientName: trim($row[2] ?? ''),
            clientPhone: trim($row[3] ?? ''),
            receivedAt: self::parseDate(trim($row[4] ?? '')),
            description: trim($row[5] ?? ''),
            deviceSerial: trim($row[6] ?? ''),
            devicePassword: trim($row[7] ?? ''),
            observations: trim($row[8] ?? ''),
            hash: md5(implode('|', array_map('trim', $row))),
        );
    }

    public function isEmpty(): bool
    {
        return $this->folio === '' && $this->device === '';
    }

    private static function normalizeFolio(string $folio): string
    {
        $stripped = str_replace('.', '', $folio);

        if ($stripped !== '' && ctype_digit($stripped)) {
            return $stripped;
        }

        return $folio;
    }

    private static function parseDate(string $value): ?string
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
}
```

### SyncResult DTO

```php
<?php

namespace App\DTOs;

readonly class SyncResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}

    public function summary(): string
    {
        return sprintf('%d new, %d updated, %d unchanged', $this->created, $this->updated, $this->skipped);
    }
}
```

### GoogleSheetsClient (Integration)

```php
<?php

namespace App\Integrations\GoogleSheets;

use App\DTOs\SheetRow;
use Illuminate\Support\Collection;
use Revolution\Google\Sheets\Facades\Sheets;

class GoogleSheetsClient
{
    /**
     * Fetch all data rows from the configured spreadsheet as DTOs.
     * Skips the first 4 rows (filter row, headers, blanks).
     *
     * @return Collection<int, SheetRow>
     */
    public function fetchRows(): Collection
    {
        $rows = Sheets::spreadsheet(config('google.sheets.spreadsheet_id'))
            ->sheet(config('google.sheets.sheet_name', 'Hoja 1'))
            ->get();

        return $rows->slice(4)->values()
            ->map(fn (array $row) => SheetRow::fromArray($row));
    }
}
```

### SyncOrdersFromSheet (Action)

```php
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

        // Bulk-load existing hashes: ['folio' => 'hash', ...]
        $existingHashes = Ticket::query()
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->pluck('tickets.sheet_row_hash', 'orders.folio')
            ->toArray();

        $rows->chunk(100)->each(function ($chunk) use ($existingHashes) {
            DB::transaction(function () use ($chunk, $existingHashes) {
                foreach ($chunk as $row) {
                    try {
                        $this->processRow($row, $existingHashes);
                    } catch (\Throwable $e) {
                        Log::warning('Sheet sync: row skipped', [
                            'folio' => $row->folio,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });

        cache()->put('sheets:last_synced_at', now());

        return new SyncResult($this->created, $this->updated, $this->skipped);
    }

    private function processRow(SheetRow $row, array $existingHashes): void
    {
        if ($row->isEmpty()) {
            return;
        }

        $folio = $row->folio !== '' ? $row->folio : 'IMP-' . $this->syntheticCounter++;

        // Hash check — skip unchanged rows
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

    // regexInfer(), findOrCreateClient(), updateExisting(), createNew()
    // — shared parsing logic, previously in ImportOrders
    // Both createNew() and updateExisting() store $row->hash as sheet_row_hash
}
```

### SyncFromSheet Command

```php
<?php

namespace App\Console\Commands;

use App\Actions\SyncOrdersFromSheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFromSheet extends Command
{
    protected $signature = 'sheets:sync';
    protected $description = 'Sync orders from Google Sheets';

    public function handle(SyncOrdersFromSheet $action): int
    {
        $this->info('Syncing from Google Sheets...');

        try {
            $result = $action();
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync failed', ['error' => $e->getMessage()]);
            $this->error('Sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done. ' . $result->summary());

        return self::SUCCESS;
    }
}
```

### Scheduler (routes/console.php)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sheets:sync')->daily();
```

### Filament Action (on dashboard or order list)

```php
use App\Actions\SyncOrdersFromSheet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

Action::make('syncFromSheet')
    ->label(function () {
        $last = cache('sheets:last_synced_at');
        return $last
            ? 'Sync from Sheet (last: ' . $last->diffForHumans() . ')'
            : 'Sync from Sheet';
    })
    ->icon(Heroicon::ArrowPath)
    ->disabled(function () {
        $last = cache('sheets:last_synced_at');
        return $last && $last->greaterThan(now()->subSeconds(30));
    })
    ->action(function () {
        try {
            $result = app(SyncOrdersFromSheet::class)();
            Notification::make()
                ->title('Sync complete')
                ->body($result->summary())
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }),
```

### Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('sheet_row_hash', 32)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('sheet_row_hash');
        });
    }
};
```

## External Services & Libraries

| Dependency | Purpose | Already installed? |
|---|---|---|
| `revolution/laravel-google-sheets` (^7.2) | Laravel wrapper for Google Sheets API v4 | Yes |
| `google/apiclient` | Underlying Google API client (pulled by above) | Yes (transitive) |
| Google Cloud service account | Authentication — JSON key in `storage/app/` | Yes, configured |
| Google Sheets API v4 | Read access to spreadsheet | Enabled in Google Console |

### Google API Quotas

- 300 read requests/minute per project
- One sync = 1 API call (full sheet read)
- Daily schedule + occasional manual sync is well within limits

## Documentation

A developer doc at `docs/google-sheets-sync.md` should cover:

- **How the sync works** — data flow from sheet to DB, with mermaid diagram
- **Source of truth** — sheet always wins, Filament edits to synced fields will be overwritten
- **Change detection** — hash fingerprint, why rows are skipped, when hashes update
- **Gotchas** — first 4 rows are skipped (filter/headers/blanks), folio normalization rules, password encryption, client dedup order of precedence
- **Configuration** — required env vars, service account setup, how to share the sheet with the service account email
- **How to extend** — adding new columns, changing sync frequency, adding error monitoring
- **Maintenance** — what happens if the sheet structure changes (column order), how to re-run a full sync
- **Turning off sync** — when the shop transitions fully to Filament, remove the schedule and action
