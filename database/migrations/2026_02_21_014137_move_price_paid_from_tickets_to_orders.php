<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable()->after('received_at');
            $table->boolean('paid')->default(false)->after('price');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['price', 'paid']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('paid')->default(false);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['price', 'paid']);
        });
    }
};
