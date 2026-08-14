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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('menunggu_pembayaran');
            $table->string('payment_mode');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('dp_amount', 12, 2)->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->text('buyer_notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('menunggu_pembayaran', 'dp_dibayar', 'produksi', 'siap_kirim', 'lunas', 'dikirim', 'delivered', 'dibatalkan_buyer', 'dibatalkan_sistem'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_mode_check CHECK (payment_mode IN ('dp', 'lunas'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
