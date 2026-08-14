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
        Schema::create('campaign_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('size');
            $table->decimal('price_override', 12, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'size']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campaign_sizes ADD CONSTRAINT campaign_sizes_size_check CHECK (size IN ('S', 'M', 'L', 'XL', 'XXL'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_sizes');
    }
};
