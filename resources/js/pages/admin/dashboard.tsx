import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    MessageSquare,
    Package,
    PackageOpen,
    PieChart,
    Shirt,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCampaignsIndex } from '@/routes/admin/campaigns';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as komunikasiIndex } from '@/routes/admin/komunikasi';
import { index as laporanIndex } from '@/routes/admin/laporan';
import { index as readyStockIndex } from '@/routes/admin/ready-stock';

type RecentOrder = {
    id: number;
    order_number: string;
    status: string;
    total_amount: string;
    created_at: string;
    items_count: number;
    user: { name: string };
};

type Stats = {
    total_campaigns: number;
    active_campaigns: number;
    total_orders: number;
    pending_orders: number;
    total_revenue: string;
    sold_items: number;
    ready_stock_count: number;
};

type Props = {
    stats: Stats;
    recent_orders: RecentOrder[];
};

const STATUS_LABELS: Record<string, string> = {
    menunggu_pembayaran: 'Menunggu Pembayaran',
    dp_dibayar: 'DP Dibayar',
    produksi: 'Produksi',
    siap_kirim: 'Siap Kirim',
    lunas: 'Lunas',
    dikirim: 'Dikirim',
    delivered: 'Delivered',
    dibatalkan_buyer: 'Dibatalkan Buyer',
    dibatalkan_sistem: 'Dibatalkan Sistem',
};

const STATUS_BADGE_CLASS: Record<string, string> = {
    menunggu_pembayaran: 'bg-amber-500 hover:bg-amber-500',
    dp_dibayar: 'bg-blue-500 hover:bg-blue-500',
    produksi: '',
    siap_kirim: 'bg-violet-500 hover:bg-violet-500',
    lunas: 'bg-green-600 hover:bg-green-600',
    dikirim: 'bg-cyan-500 hover:bg-cyan-500',
    delivered: 'bg-green-700 hover:bg-green-700',
    dibatalkan_buyer: 'bg-red-600 hover:bg-red-600',
    dibatalkan_sistem: 'bg-red-700 hover:bg-red-700',
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    const n = Number(value);

    if (Number.isNaN(n)) {
        return '—';
    }

    return rupiah.format(n);
}

function formatDate(value: string): string {
    const d = new Date(value);

    if (Number.isNaN(d.getTime())) {
        return value;
    }

    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function StatCard({
    label,
    value,
    icon: Icon,
    testId,
}: {
    label: string;
    value: string;
    icon: typeof Package;
    testId: string;
}) {
    return (
        <Card data-test="stat-card" className="gap-2 p-4">
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span className="flex size-8 items-center justify-center rounded-lg bg-muted">
                    <Icon className="size-4 text-muted-foreground" />
                </span>
            </div>
            <p
                data-test={testId}
                className="text-2xl font-semibold tabular-nums"
            >
                {value}
            </p>
        </Card>
    );
}

function QuickLink({
    href,
    icon: Icon,
    label,
    testId,
}: {
    href: string;
    icon: typeof Package;
    label: string;
    testId?: string;
}) {
    return (
        <Button
            asChild
            variant="outline"
            className="h-auto justify-start gap-3 py-4"
            data-test={testId}
        >
            <Link href={href}>
                <span className="flex size-8 items-center justify-center rounded-lg bg-muted">
                    <Icon className="size-4" />
                </span>
                <span className="flex-1 text-left font-medium">{label}</span>
                <ArrowRight className="size-4 text-muted-foreground" />
            </Link>
        </Button>
    );
}

export default function AdminDashboard({ stats, recent_orders }: Props) {
    return (
        <>
            <Head title="Dashboard Admin" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Dashboard</h1>
                    <p className="text-sm text-muted-foreground">
                        Ringkasan performa toko kamu
                    </p>
                </div>

                {/* Stats grid */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <StatCard
                        label="Total Kampanye"
                        value={String(stats.total_campaigns)}
                        icon={Shirt}
                        testId="stat-total-campaigns"
                    />
                    <StatCard
                        label="Kampanye Aktif"
                        value={String(stats.active_campaigns)}
                        icon={Shirt}
                        testId="stat-active-campaigns"
                    />
                    <StatCard
                        label="Total Order"
                        value={String(stats.total_orders)}
                        icon={ShoppingCart}
                        testId="stat-total-orders"
                    />
                    <StatCard
                        label="Order Menunggu Bayar"
                        value={String(stats.pending_orders)}
                        icon={Package}
                        testId="stat-pending-orders"
                    />
                    <StatCard
                        label="Total Revenue"
                        value={formatPrice(stats.total_revenue)}
                        icon={Wallet}
                        testId="stat-total-revenue"
                    />
                    <StatCard
                        label="Barang Terjual / Ready Stock"
                        value={`${stats.sold_items} / ${stats.ready_stock_count}`}
                        icon={PackageOpen}
                        testId="stat-sold-ready"
                    />
                </div>

                {/* Recent orders + Quick links */}
                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    {/* Recent orders */}
                    <Card className="gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">Pesanan Terbaru</h2>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={adminOrdersIndex.url()}>
                                    Lihat semua
                                </Link>
                            </Button>
                        </div>
                        {recent_orders.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Belum ada pesanan masuk.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-2.5 font-medium">
                                                No. Pesanan
                                            </th>
                                            <th className="px-3 py-2.5 font-medium">
                                                Pembeli
                                            </th>
                                            <th className="px-3 py-2.5 text-center font-medium">
                                                Item
                                            </th>
                                            <th className="px-3 py-2.5 text-center font-medium">
                                                Status
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium">
                                                Total
                                            </th>
                                            <th className="px-3 py-2.5 font-medium">
                                                Tanggal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recent_orders.map((order) => (
                                            <tr
                                                key={order.id}
                                                data-test="recent-order-row"
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-3 py-2.5">
                                                    <Link
                                                        href={adminOrdersIndex.url()}
                                                        className="font-mono font-medium hover:text-primary"
                                                    >
                                                        {order.order_number}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    {order.user.name}
                                                </td>
                                                <td className="px-3 py-2.5 text-center tabular-nums">
                                                    {order.items_count}
                                                </td>
                                                <td className="px-3 py-2.5 text-center">
                                                    <Badge
                                                        variant={
                                                            order.status.startsWith(
                                                                'dibatalkan',
                                                            )
                                                                ? 'destructive'
                                                                : 'default'
                                                        }
                                                        className={
                                                            STATUS_BADGE_CLASS[
                                                                order.status
                                                            ] ?? ''
                                                        }
                                                    >
                                                        {STATUS_LABELS[
                                                            order.status
                                                        ] ?? order.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-medium tabular-nums">
                                                    {formatPrice(
                                                        order.total_amount,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                    {formatDate(
                                                        order.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    {/* Quick links */}
                    <div className="flex flex-col gap-3">
                        <h2 className="font-medium">Akses Cepat</h2>
                        <div className="flex flex-col gap-2">
                            <QuickLink
                                href={adminCampaignsIndex.url()}
                                icon={Shirt}
                                label="Kelola Kampanye"
                                testId="quick-link-campaigns"
                            />
                            <QuickLink
                                href={adminOrdersIndex.url()}
                                icon={Package}
                                label="Kelola Pesanan"
                                testId="quick-link-orders"
                            />
                            <QuickLink
                                href={komunikasiIndex.url()}
                                icon={MessageSquare}
                                label="Komunikasi"
                                testId="quick-link-komunikasi"
                            />
                            <QuickLink
                                href={readyStockIndex.url()}
                                icon={PackageOpen}
                                label="Ready Stock"
                                testId="quick-link-ready-stock"
                            />
                            <QuickLink
                                href={laporanIndex.url()}
                                icon={PieChart}
                                label="Laporan Keuangan"
                                testId="quick-link-laporan"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: adminDashboard.url() }],
};
