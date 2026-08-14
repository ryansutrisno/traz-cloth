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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->integer('quota');
            $table->integer('sold_count')->default(0);
            $table->string('status')->default('draft');
            $table->boolean('ready_stock_enabled')->default(true);
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_check CHECK (status IN ('draft', 'active', 'sold_out', 'disabled'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
