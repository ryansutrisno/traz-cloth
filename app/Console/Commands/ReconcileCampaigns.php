<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\OrderItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

#[Signature('app:reconcile-campaigns')]
#[Description('Audit read-only kuota dan stok kampanye.')]
class ReconcileCampaigns extends Command
{
    /** @var array<int, array<int, int|string>> */
    private array $rows = [];

    public function handle(): int
    {
        $hasFindings = false;

        foreach (Campaign::query()->with('sizes')->orderBy('id')->get() as $campaign) {
            $expectedSoldCount = $this->expectedSoldCount($campaign->id);
            $soldCountDifference = $campaign->sold_count - $expectedSoldCount;
            $soldCountStatus = $soldCountDifference === 0 ? 'OK' : 'DRIFT';
            $hasFindings = $hasFindings || $soldCountStatus === 'DRIFT';

            $this->rows[] = [
                $campaign->title,
                'agregat sold_count',
                $campaign->sold_count,
                $expectedSoldCount,
                $soldCountDifference,
                $soldCountStatus,
            ];

            $quotaDifference = $campaign->sold_count - $campaign->quota;
            $quotaStatus = $quotaDifference > 0 ? 'DRIFT' : 'OK';
            $hasFindings = $hasFindings || $quotaStatus === 'DRIFT';
            $this->rows[] = [
                $campaign->title,
                'quota',
                $campaign->sold_count,
                $campaign->quota,
                $quotaDifference,
                $quotaStatus,
            ];

            foreach ($campaign->sizes as $size) {
                $sizeStatus = $size->stock < 0 ? 'DRIFT' : 'OK';
                $hasFindings = $hasFindings || $sizeStatus === 'DRIFT';
                $this->rows[] = [
                    $campaign->title,
                    'ukuran '.$size->size->value,
                    $size->stock,
                    '-',
                    '-',
                    $sizeStatus,
                ];
            }
        }

        $this->table(['Kampanye', 'Ukuran/agregat', 'Nilai DB', 'Ekspektasi', 'Selisih', 'Status'], $this->rows);

        if ($hasFindings) {
            $this->error('Campaign reconciliation FAILED: findings detected.');

            return self::FAILURE;
        }

        $this->info('Campaign reconciliation OK');

        return self::SUCCESS;
    }

    private function expectedSoldCount(int $campaignId): int
    {
        $count = OrderItem::query()
            ->where('campaign_id', $campaignId)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('order', function (Builder $query): void {
                        $query->whereIn('status', $this->countedStatuses());
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereHas('order', function (Builder $query): void {
                                $query->whereIn('status', [
                                    OrderStatus::DibatalkanBuyer->value,
                                    OrderStatus::DibatalkanSistem->value,
                                ]);
                            })
                            ->whereExists(function (QueryBuilder $query): void {
                                $query->select(DB::raw('1'))
                                    ->from('order_status_histories')
                                    ->whereColumn('order_status_histories.order_id', 'order_items.order_id')
                                    ->whereIn('order_status_histories.status', [
                                        OrderStatus::Produksi->value,
                                        OrderStatus::SiapKirim->value,
                                    ]);
                            });
                    });
            })
            ->selectRaw('COALESCE(SUM(qty - ready_stock_qty), 0) AS total')
            ->value('total');

        return (int) $count;
    }

    /** @return array<int, string> */
    private function countedStatuses(): array
    {
        return [
            OrderStatus::DpDibayar->value,
            OrderStatus::Produksi->value,
            OrderStatus::SiapKirim->value,
            OrderStatus::Lunas->value,
            OrderStatus::Dikirim->value,
            OrderStatus::Delivered->value,
        ];
    }
}
