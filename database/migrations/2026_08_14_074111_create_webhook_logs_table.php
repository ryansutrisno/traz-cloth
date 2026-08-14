<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event', 100)->nullable();
            $table->string('event_id', 255)->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE webhook_logs ADD CONSTRAINT webhook_logs_provider_check CHECK (provider IN ('midtrans', 'biteship'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
