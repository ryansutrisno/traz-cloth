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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_type');
            $table->string('payment_status')->default('menunggu');
            $table->decimal('amount', 12, 2);
            $table->string('midtrans_order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_type_check CHECK (payment_type IN ('dp', 'full', 'pelunasan'))");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_status_check CHECK (payment_status IN ('menunggu', 'sukses', 'gagal', 'kedaluwarsa', 'refund'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
