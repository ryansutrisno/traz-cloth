import { Head, Link } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as ordersIndex, show as ordersShow } from '@/routes/orders';

type OrderRow = {
    id: number;
    order_number: string;
    status: string;
    payment_mode: 'dp' | 'lunas';
    total_amount: string;
    created_at: string;
    items_count: number;
    resi_number: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    orders: {
        data: OrderRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters?: { q: string };
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

const PAYMENT_MODE_LABELS: Record<string, string> = {
    dp: 'DP',
    lunas: 'Lunas',
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

function decodeLabel(label: string): string {
    if (label.includes('Previous')) {
        return '‹';
    }

    if (label.includes('Next')) {
        return '›';
    }

    return label;
}

export default function OrdersIndex({ orders }: Props) {
    if (orders.data.length === 0) {
        return (
            <>
                <Head title="Pesanan Saya" />
                <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 py-20 text-center">
                    <PackageOpen className="size-12 text-muted-foreground/40" />
                    <p className="text-lg font-medium">Belum ada pesanan</p>
                    <p className="text-sm text-muted-foreground">
                        Yuk mulai belanja desain kaos pre-order favorit kamu.
                    </p>
                    <Button asChild>
                        <Link href={campaignsIndex.url()}>
                            Lihat Koleksi Kampanye
                        </Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Pesanan Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pesanan Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pantau status pesanan pre-order kamu
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    {orders.data.map((order) => (
                        <Link
                            key={order.id}
                            href={ordersShow.url({ order: order.id })}
                            data-test="buyer-order-row"
                            className="block focus-visible:outline-none"
                        >
                            <Card className="gap-0 p-4 transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <span
                                            data-test="buyer-order-number"
                                            className="font-mono font-medium"
                                        >
                                            {order.order_number}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(order.created_at)}
                                        </span>
                                    </div>
                                    <Badge
                                        data-test="buyer-order-status-badge"
                                        variant={
                                            order.status.startsWith(
                                                'dibatalkan',
                                            )
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        className={
                                            STATUS_BADGE_CLASS[order.status] ??
                                            ''
                                        }
                                    >
                                        {STATUS_LABELS[order.status] ??
                                            order.status}
                                    </Badge>
                                    {order.resi_number && (
                                        <Badge
                                            data-test="buyer-order-shipping-badge"
                                            variant="secondary"
                                        >
                                            Dikirim · {order.resi_number}
                                        </Badge>
                                    )}
                                </div>
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                        <span>{order.items_count} item</span>
                                        <Badge variant="secondary">
                                            {
                                                PAYMENT_MODE_LABELS[
                                                    order.payment_mode
                                                ]
                                            }
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-medium tabular-nums">
                                            {formatPrice(order.total_amount)}
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <span>Lihat Detail</span>
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        </Link>
                    ))}
                </div>

                {/* Pagination */}
                {orders.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {orders.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                asChild
                                disabled={!link.url}
                            >
                                {link.url ? (
                                    <Link href={link.url}>
                                        {decodeLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span className="opacity-50">
                                        {decodeLabel(link.label)}
                                    </span>
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Pesanan Saya', href: ordersIndex.url() }],
};
