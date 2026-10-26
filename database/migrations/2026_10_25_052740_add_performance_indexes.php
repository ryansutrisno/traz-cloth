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
        $this->addIndex('orders', ['status', 'created_at'], 'orders_status_created_at_index');
        $this->addIndex('orders', ['status', 'payment_due_at'], 'orders_status_payment_due_at_index');
        $this->addIndex('order_items', ['order_id'], 'order_items_order_id_index');
        $this->addIndex('order_items', ['campaign_id'], 'order_items_campaign_id_index');
        $this->addIndex('payments', ['order_id'], 'payments_order_id_index');
        $this->addIndex('payments', ['payment_status'], 'payments_payment_status_index');
        $this->addIndex('campaign_sizes', ['campaign_id', 'enabled'], 'campaign_sizes_campaign_id_enabled_index');
        $this->addIndex('campaign_sizes', ['stock'], 'campaign_sizes_stock_index');
        $this->addIndex('campaign_images', ['campaign_id', 'is_primary'], 'campaign_images_campaign_id_is_primary_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'orders_status_created_at_index' => 'orders',
            'orders_status_payment_due_at_index' => 'orders',
            'order_items_order_id_index' => 'order_items',
            'order_items_campaign_id_index' => 'order_items',
            'payments_order_id_index' => 'payments',
            'payments_payment_status_index' => 'payments',
            'campaign_sizes_campaign_id_enabled_index' => 'campaign_sizes',
            'campaign_sizes_stock_index' => 'campaign_sizes',
            'campaign_images_campaign_id_is_primary_index' => 'campaign_images',
        ] as $index => $table) {
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->dropIndex($index));
            }
        }
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table) || $this->hasColumnsIndex($table, $columns)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->index($columns, $index));
    }

    /** @param array<int, string> $columns */
    private function hasColumnsIndex(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $existing): bool => $existing['columns'] === $columns,
        );
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $existing): bool => $existing['name'] === $index,
        );
    }
};
