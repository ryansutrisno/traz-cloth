import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as campaignsIndex } from '@/routes/campaigns';
import {
    index as ordersIndex,
    payRemaining,
    show as ordersShow,
} from '@/routes/orders';

type OrderItem = {
    id: number;
    title: string;
    size: string;
    qty: number;
    unit_price: string;
    total_price: string;
};

type Payment = {
    id: number;
    payment_type: 'dp' | 'full' | 'pelunasan';
    payment_status: string;
    amount: string;
    paid_at: string | null;
};

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_mode: 'dp' | 'lunas';
    subtotal: string;
    dp_amount: string | null;
    total_amount: string;
    payment_due_at: string | null;
    created_at: string;
    resi_number?: string | null;
    courier_code?: string | null;
    courier_service?: string | null;
    items: OrderItem[];
    payments: Payment[];
};

type Props = {
    order: Order;
    can_pay_remaining: boolean;
    remaining_amount: string | null;
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

const PAYMENT_TYPE_LABELS: Record<string, string> = {
    dp: 'DP',
    full: 'Lunas',
    pelunasan: 'Pelunasan',
};

const PAYMENT_STATUS_LABELS: Record<string, string> = {
    menunggu: 'Menunggu',
    sukses: 'Sukses',
    gagal: 'Gagal',
    kedaluwarsa: 'Kedaluwarsa',
    refund: 'Refund',
};

const PAYMENT_STATUS_BADGE_CLASS: Record<string, string> = {
    menunggu: 'bg-amber-500 hover:bg-amber-500',
    sukses: 'bg-green-600 hover:bg-green-600',
    gagal: 'bg-red-600 hover:bg-red-600',
    kedaluwarsa: '',
    refund: 'bg-red-600 hover:bg-red-600',
};

const PAYMENT_MODE_LABELS: Record<string, string> = {
    dp: 'DP 50%',
    lunas: 'Lunas (Langsung)',
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

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const d = new Date(value);

    if (Number.isNaN(d.getTime())) {
        return value;
    }

    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function OrdersShow({
    order,
    can_pay_remaining,
    remaining_amount,
}: Props) {
    const [paying, setPaying] = useState(false);

    const handlePayRemaining = () => {
        setPaying(true);
        router.post(
            payRemaining.url({ order: order.id }),
            {},
            {
                onError: (err) => {
                    setPaying(false);
                    const msg =
                        (err.order as string | undefined) ??
                        'Gagal memulai pembayaran';
                    toast.error(msg);
                },
            },
        );
        // onSuccess diabaikan: server redirect eksternal ke Midtrans/dev.
    };

    return (
        <>
            <Head title={`Pesanan ${order.order_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Back + header */}
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={ordersIndex.url()} className="gap-1">
                            <ArrowLeft className="size-4" />
                            Kembali ke pesanan saya
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1
                            data-test="order-number"
                            className="font-mono text-xl font-semibold tracking-tight md:text-2xl"
                        >
                            {order.order_number}
                        </h1>
                        <Badge
                            data-test="buyer-order-status-badge"
                            variant={
                                order.status.startsWith('dibatalkan')
                                    ? 'destructive'
                                    : 'default'
                            }
                            className={STATUS_BADGE_CLASS[order.status] ?? ''}
                        >
                            {STATUS_LABELS[order.status] ?? order.status}
                        </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Dibuat {formatDate(order.created_at)}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    {/* Left */}
                    <div className="flex flex-col gap-5">
                        {/* Items */}
                        <Card className="gap-3 p-4">
                            <h2 className="font-medium">
                                Item Pesanan ({order.items.length})
                            </h2>
                            <div className="flex flex-col divide-y">
                                {order.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex flex-col gap-1">
                                            <span className="text-sm font-medium">
                                                {item.title}
                                            </span>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Badge variant="secondary">
                                                    {item.size}
                                                </Badge>
                                                <span>
                                                    {item.qty} ×{' '}
                                                    {formatPrice(
                                                        item.unit_price,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <span className="text-sm font-medium tabular-nums">
                                            {formatPrice(item.total_price)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Payments */}
                        <Card className="gap-3 p-4">
                            <h2 className="font-medium">
                                Pembayaran ({order.payments.length})
                            </h2>
                            {order.payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada pembayaran tercatat.
                                </p>
                            ) : (
                                <div className="flex flex-col divide-y">
                                    {order.payments.map((payment) => (
                                        <div
                                            key={payment.id}
                                            className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {
                                                            PAYMENT_TYPE_LABELS[
                                                                payment
                                                                    .payment_type
                                                            ]
                                                        }
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            payment.payment_status ===
                                                                'gagal' ||
                                                            payment.payment_status ===
                                                                'refund'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        className={
                                                            PAYMENT_STATUS_BADGE_CLASS[
                                                                payment
                                                                    .payment_status
                                                            ] ?? ''
                                                        }
                                                    >
                                                        {
                                                            PAYMENT_STATUS_LABELS[
                                                                payment
                                                                    .payment_status
                                                            ]
                                                        }
                                                    </Badge>
                                                </div>
                                                <span className="text-sm font-medium tabular-nums">
                                                    {formatPrice(
                                                        payment.amount,
                                                    )}
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Dibayar:{' '}
                                                {formatDate(payment.paid_at)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        {/* Resi */}
                        {order.resi_number && (
                            <Card className="gap-3 p-4">
                                <h2 className="font-medium">Resi Pengiriman</h2>
                                <div className="flex flex-col gap-1 rounded-lg bg-muted/50 p-3 text-sm">
                                    <div className="flex items-center gap-2">
                                        <span className="text-muted-foreground">
                                            Nomor resi:
                                        </span>
                                        <span
                                            data-test="resi-number"
                                            className="font-mono font-medium"
                                        >
                                            {order.resi_number}
                                        </span>
                                    </div>
                                    {(order.courier_code ||
                                        order.courier_service) && (
                                        <p className="text-xs text-muted-foreground">
                                            {order.courier_code?.toUpperCase()}{' '}
                                            {order.courier_service}
                                        </p>
                                    )}
                                    {order.courier_code && (
                                        <p className="text-xs text-muted-foreground">
                                            Lacak di {order.courier_code}
                                        </p>
                                    )}
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* Right summary */}
                    <div className="lg:sticky lg:top-6 lg:self-start">
                        <Card className="gap-4 p-4">
                            <h2 className="font-medium">Ringkasan</h2>
                            <div className="flex flex-col gap-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Mode bayar
                                    </span>
                                    <span className="font-medium">
                                        {PAYMENT_MODE_LABELS[
                                            order.payment_mode
                                        ] ?? order.payment_mode}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Subtotal
                                    </span>
                                    <span className="tabular-nums">
                                        {formatPrice(order.subtotal)}
                                    </span>
                                </div>
                                {order.payment_mode === 'dp' && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            DP dibayar
                                        </span>
                                        <span className="tabular-nums">
                                            {formatPrice(order.dp_amount)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between border-t pt-2 font-medium">
                                    <span>Total</span>
                                    <span className="text-lg tabular-nums">
                                        {formatPrice(order.total_amount)}
                                    </span>
                                </div>
                                {order.payment_due_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Jatuh tempo pelunasan
                                        </span>
                                        <span>
                                            {formatDate(order.payment_due_at)}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Pay remaining */}
                            {can_pay_remaining && (
                                <div className="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30">
                                    <p className="text-sm text-amber-900 dark:text-amber-200">
                                        Sisa pelunasan:{' '}
                                        <span className="font-semibold tabular-nums">
                                            {formatPrice(remaining_amount)}
                                        </span>
                                    </p>
                                    <Button
                                        type="button"
                                        className="w-full"
                                        data-test="pay-remaining-button"
                                        disabled={paying}
                                        onClick={handlePayRemaining}
                                    >
                                        {paying
                                            ? 'Memproses...'
                                            : 'Bayar Pelunasan'}
                                    </Button>
                                </div>
                            )}

                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={campaignsIndex.url()}>
                                    Lanjut Belanja
                                </Link>
                            </Button>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

OrdersShow.layout = {
    breadcrumbs: [
        { title: 'Pesanan Saya', href: ordersIndex.url() },
        { title: 'Detail', href: ordersShow.url({ order: 0 }) },
    ],
};
