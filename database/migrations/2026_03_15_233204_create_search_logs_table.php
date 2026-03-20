<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->string('parsed_client')->nullable();
            $table->string('parsed_device')->nullable();
            $table->string('parsed_status')->nullable();
            $table->boolean('is_fallback')->default(false);
            $table->unsignedInteger('order_results')->default(0);
            $table->unsignedInteger('client_results')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
