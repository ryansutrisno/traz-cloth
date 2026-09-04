import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    HeartHandshake,
    MapPin,
    Package,
    ShoppingBag,
} from 'lucide-react';
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
    const firstName = userName.split(' ')[0];

    return (
        <>
            <Head title="Akun Saya" />
            <div className="flex flex-col gap-8">
                {/* Sambutan */}
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Halo kembali, {firstName}.
                    </h1>
                    <p className="text-base text-muted-foreground">
                        Ringkasan pre-order dan aktivitas akun kamu.
                    </p>
                </header>

                {/* Bento grid */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-12">
                    {/* Kartu ringkasan */}
                    <Card className="flex flex-col justify-between gap-6 rounded-xl p-6 shadow-sm md:col-span-4 md:p-8">
                        <div className="flex flex-col gap-2">
                            <h2 className="text-xl font-semibold tracking-tight">
                                Aktivitas Kamu
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Riwayat pre-order dan alamat pengiriman yang
                                kamu simpan.
                            </p>
                        </div>
                        <div className="flex items-end justify-between">
                            <div>
                                <span
                                    data-test="dashboard-orders-count"
                                    className="block text-4xl font-bold text-primary tabular-nums"
                                >
                                    {orders_count}
                                </span>
                                <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Total Pesanan
                                </span>
                            </div>
                            <HeartHandshake className="size-10 text-primary/50" />
                        </div>
                        <div className="flex items-center justify-between border-t pt-4 text-sm">
                            <span className="text-muted-foreground">
                                Alamat tersimpan
                            </span>
                            <span
                                data-test="dashboard-addresses-count"
                                className="font-semibold tabular-nums"
                            >
                                {addresses_count}
                            </span>
                        </div>
                    </Card>

                    {/* Pesanan terbaru */}
                    <Card className="gap-4 rounded-xl p-6 shadow-sm md:col-span-8 md:p-8">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xl font-semibold tracking-tight">
                                Pesanan Terbaru
                            </h2>
                            <Link
                                href={ordersIndex.url()}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Lihat semua
                            </Link>
                        </div>

                        {recent_orders.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Belum ada pesanan. Yuk mulai belanja!
                            </p>
                        ) : (
                            <div className="flex flex-col divide-y">
                                {recent_orders.map((order) => (
                                    <Link
                                        key={order.id}
                                        href={ordersShow.url({
                                            order: order.id,
                                        })}
                                        data-test="dashboard-recent-order-row"
                                        className="-mx-2 flex items-center gap-4 rounded-lg p-3 transition-colors hover:bg-muted/40 focus-visible:outline-none"
                                    >
                                        <span className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-muted">
                                            <Package className="size-6 text-muted-foreground" />
                                        </span>
                                        <div className="flex flex-1 flex-col gap-1">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-mono text-sm font-medium">
                                                    {order.order_number}
                                                </span>
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
                                            </div>
                                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {formatDate(
                                                        order.created_at,
                                                    )}
                                                </span>
                                                <span>
                                                    {order.items_count} item ·{' '}
                                                    <span className="font-medium tabular-nums">
                                                        {formatPrice(
                                                            order.total_amount,
                                                        )}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>

                    {/* Akses cepat */}
                    <div className="flex flex-col gap-3 md:col-span-12">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Akses Cepat
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Button
                                asChild
                                variant="outline"
                                className="h-auto justify-start gap-3 py-4"
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
                                className="h-auto justify-start gap-3 py-4"
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
                                className="h-auto justify-start gap-3 py-4"
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
                </div>
            </div>
        </>
    );
}
