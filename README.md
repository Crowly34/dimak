# Dimak — Mac Repair Shop Management

Internal admin panel for managing Mac repair orders, built with Laravel 12 + Filament 5.

## Tech Stack

- **Laravel 12** — backend framework
- **Filament 5** — admin panel (Livewire + Tailwind)
- **SQLite** — local dev database (via Laravel Herd)
- **Pest 4** — test runner
- **owen-it/laravel-auditing** — full audit trail on models

## Setup

```bash
composer run setup
```

Installs dependencies, copies `.env`, generates an app key, runs migrations, and builds frontend assets.

The app is served automatically by **Laravel Herd** at `https://dimak.test`.

## Development

```bash
composer run dev   # starts server, queue, logs watcher, and Vite in parallel
```

| Task | Command |
|------|---------|
| Run tests | `php artisan test --compact` |
| Static analysis | `composer run stan` |
| Format PHP | `vendor/bin/pint --dirty` |
| Import CSV | `php artisan import:orders {file} [--dry-run]` |

## Data Model

An **Order** is a client visit receipt — it holds only `folio`, `client_id`, and `received_at`.

A **Ticket** is a single device under repair attached to an Order. All repair details (device, status, location, price, password) live on the Ticket. One Order can have many Tickets.

```
Client → hasMany → Order → hasMany → Ticket → hasMany → TicketStatusLog
```

## Key Conventions

- **Status changes** go through the Filament "Change Status" action, not direct `$ticket->update()`. This creates a `TicketStatusLog` and suppresses the generic audit event to avoid double-logging.
- **Device passwords** are encrypted at rest by an Eloquent accessor/mutator on `Ticket`, so no call site can persist plaintext by forgetting to encrypt. Reads return `null` if a value cannot be decrypted rather than throwing.
- **Auditing** tracks all Order and Ticket changes except `device_password` (excluded for privacy).
