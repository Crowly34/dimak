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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('device', 80);
            $table->string('device_serial', 80)->nullable();
            $table->text('device_password')->nullable();
            $table->date('received_at')->nullable();
            $table->text('description')->nullable();
            $table->text('observations')->nullable();
            $table->string('status')->default('pending_diagnosis');
            $table->string('location')->default('shop');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('paid')->default(false);
            $table->date('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
