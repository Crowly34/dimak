<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('device', 80);
            $table->string('device_serial', 80)->nullable();
            $table->text('device_password')->nullable();
            $table->date('delivered_at')->nullable();
            $table->text('description')->nullable();
            $table->text('observations')->nullable();
            $table->string('status')->default('pending_diagnosis');
            $table->string('location')->default('shop');
            $table->string('sheet_row_hash', 32)->nullable()->after('location');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
