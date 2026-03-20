<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX clients_name_trgm_idx ON clients USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX orders_folio_trgm_idx ON orders USING gin (folio gin_trgm_ops)');
        DB::statement('CREATE INDEX tickets_device_trgm_idx ON tickets USING gin (device gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS tickets_device_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS orders_folio_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS clients_name_trgm_idx');

        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
