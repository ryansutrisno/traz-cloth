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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->string('courier_code')->nullable();
            $table->string('courier_service')->nullable();
            $table->string('resi_number')->nullable();
            $table->string('biteship_order_id')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn([
                'address_id',
                'shipping_cost',
                'courier_code',
                'courier_service',
                'resi_number',
                'biteship_order_id',
                'shipped_at',
                'delivered_at',
            ]);
        });
    }
};
