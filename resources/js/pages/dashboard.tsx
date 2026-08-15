import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, MapPin, Package, ShoppingBag } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as addressesIndex } from '@/routes/addresses';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as ordersIndex, show as ordersShow } from '@/routes/orders';

type RecentOrder = {
    id: number;
    order_number: string;
    status: string;
    total_amount: string;
    created_at: string;
    items_count: number;
};

type Props = {
    recent_orders: RecentOrder[];
    orders_count: number;
    addresses_count: number;
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

export default function Dashboard({
    recent_orders,
    orders_count,
    addresses_count,
}: Props) {
    const { auth } = usePage().props;
    const userName = auth.user?.name ?? 'Pembeli';

    return (
        <>
            <Head title="Akun Saya" />
            <div className="flex flex-col gap-4 md:gap-6">
                {/* Greeting */}
                <div className="flex flex-col gap-1 pt-2">
                    <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                        Halo, {userName}! 👋
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola pesanan dan alamat kamu di sini.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-3">
                    <Card className="gap-1 p-3 sm:p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground sm:text-sm">
                                Total Pesanan
                            </span>
                            <Package className="size-4 text-muted-foreground" />
                        </div>
                        <p
                            data-test="dashboard-orders-count"
                            className="text-xl font-semibold tabular-nums sm:text-2xl"
                        >
                            {orders_count}
                        </p>
                    </Card>
                    <Card className="gap-1 p-3 sm:p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground sm:text-sm">
                                Alamat Tersimpan
                            </span>
                            <MapPin className="size-4 text-muted-foreground" />
                        </div>
                        <p
                            data-test="dashboard-addresses-count"
                            className="text-xl font-semibold tabular-nums sm:text-2xl"
                        >
                            {addresses_count}
                        </p>
                    </Card>
                </div>

                {/* Quick links */}
                <div className="flex flex-col gap-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Akses Cepat
                    </h2>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <Button
                            asChild
                            variant="outline"
                            className="h-auto justify-start gap-3 py-3"
                            data-test="dashboard-quick-link-orders"
                        >
                            <Link href={ordersIndex.url()}>
                                <Package className="size-5 shrink-0" />
                                <span className="flex-1 text-left font-medium">
                                    Pesanan Saya
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground" />
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="h-auto justify-start gap-3 py-3"
                            data-test="dashboard-quick-link-addresses"
                        >
                            <Link href={addressesIndex.url()}>
                                <MapPin className="size-5 shrink-0" />
                                <span className="flex-1 text-left font-medium">
                                    Alamat Saya
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground" />
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="h-auto justify-start gap-3 py-3"
                        >
                            <Link href={campaignsIndex.url()}>
                                <ShoppingBag className="size-5 shrink-0" />
                                <span className="flex-1 text-left font-medium">
                                    Lanjut Belanja
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground" />
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Recent orders */}
                <div className="flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Pesanan Terbaru
                        </h2>
                        <Button asChild size="sm" variant="ghost">
                            <Link href={ordersIndex.url()}>Lihat semua</Link>
                        </Button>
                    </div>
                    {recent_orders.length === 0 ? (
                        <Card className="gap-1 p-6 text-center">
                            <p className="text-sm text-muted-foreground">
                                Belum ada pesanan. Yuk mulai belanja!
                            </p>
                        </Card>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {recent_orders.map((order) => (
                                <Link
                                    key={order.id}
                                    href={ordersShow.url({ order: order.id })}
                                    data-test="dashboard-recent-order-row"
                                    className="block focus-visible:outline-none"
                                >
                                    <Card className="gap-2 p-3 transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring sm:p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="font-mono text-sm font-medium">
                                                    {order.order_number}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        order.created_at,
                                                    )}
                                                </span>
                                            </div>
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
                                                {STATUS_LABELS[order.status] ??
                                                    order.status}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {order.items_count} item
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    order.total_amount,
                                                )}
                                            </span>
                                        </div>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
