import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    index as adminOrdersIndex,
    bookCourier,
    courierRates,
    transition,
} from '@/routes/admin/orders';

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
    midtrans_order_id: string | null;
};

type History = {
    id: number;
    status: string;
    note: string | null;
    created_at: string;
};

type Address = {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    subdistrict: string;
    postal_code: string;
    full_address: string;
};

type CourierRate = {
    courier_code: string;
    courier_service_code: string;
    service_type: string;
    price: number;
    duration: string;
    shipment_duration_range: string;
};

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_mode: 'dp' | 'lunas';
    subtotal: string;
    discount_amount: number;
    coupon_code: string | null;
    dp_amount: string | null;
    total_amount: string;
    payment_due_at: string | null;
    buyer_notes: string | null;
    created_at: string;
    resi_number?: string | null;
    courier_code?: string | null;
    courier_service?: string | null;
    user: { name: string; email: string; phone: string };
    items: OrderItem[];
    payments: Payment[];
    histories: History[];
};

type StatusOption = { value: string; label: string };

type Props = {
    order: Order;
    statuses: StatusOption[];
    can_pay_remaining: boolean;
    remaining_amount: string | null;
    address: Address | null;
    courier_rates: CourierRate[] | null;
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

export default function AdminOrdersShow({
    order,
    statuses,
    can_pay_remaining,
    remaining_amount,
    address,
    courier_rates,
}: Props) {
    const [nextStatus, setNextStatus] = useState('');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);
    const [selectedRate, setSelectedRate] = useState<CourierRate | null>(null);
    const [rating, setRating] = useState(false);
    const [booking, setBooking] = useState(false);

    const handleTransition = () => {
        if (!nextStatus) {
            toast.error('Pilih status terlebih dahulu');

            return;
        }

        setSaving(true);
        router.post(
            transition.url({ order: order.id }),
            { status: nextStatus, note },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                    setNextStatus('');
                    setNote('');
                    toast.success('Status diperbarui');
                },
                onError: (err) => {
                    setSaving(false);
                    const msg =
                        (err.status as string | undefined) ??
                        'Gagal memperbarui status';
                    toast.error(msg);
                },
            },
        );
    };

    const handleGetCourierRates = () => {
        setRating(true);
        router.post(
            courierRates.url({ order: order.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRating(false),
                onError: (err) => {
                    const msg = (err.courier as string | undefined) ?? 'Gagal';
                    toast.error(msg);
                },
            },
        );
    };

    const handleBookCourier = () => {
        if (!selectedRate) {
            toast.error('Pilih kurir dulu');

            return;
        }

        setBooking(true);
        router.post(
            bookCourier.url({ order: order.id }),
            {
                courier_code: selectedRate.courier_code,
                courier_service: selectedRate.courier_service_code,
                shipping_cost: selectedRate.price,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBooking(false);
                    toast.success('Kurir di-book');
                },
                onError: (err) => {
                    setBooking(false);
                    const msg =
                        (err.courier as string | undefined) ??
                        (err.status as string | undefined) ??
                        'Gagal';
                    toast.error(msg);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Pesanan ${order.order_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Back + header */}
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={adminOrdersIndex.url()} className="gap-1">
                            <ArrowLeft className="size-4" />
                            Kembali ke daftar pesanan
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
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    {/* Left column */}
                    <div className="flex flex-col gap-5">
                        {/* Buyer */}
                        <Card className="gap-3 p-4">
                            <h2 className="font-medium">Data Pemesan</h2>
                            <div className="grid gap-1 text-sm">
                                <p>
                                    <span className="text-muted-foreground">
                                        Nama:{' '}
                                    </span>
                                    {order.user.name}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Email:{' '}
                                    </span>
                                    {order.user.email}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        WhatsApp:{' '}
                                    </span>
                                    {order.user.phone}
                                </p>
                            </div>
                        </Card>

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
                            <div className="flex flex-col gap-2 border-t pt-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Subtotal
                                    </span>
                                    <span className="tabular-nums">
                                        {formatPrice(order.subtotal)}
                                    </span>
                                </div>
                                {order.discount_amount > 0 && (
                                    <div className="flex items-center justify-between text-emerald-600">
                                        <span>Diskon</span>
                                        <span className="tabular-nums">
                                            -
                                            {formatPrice(
                                                String(order.discount_amount),
                                            )}
                                        </span>
                                    </div>
                                )}
                                {order.coupon_code && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Kupon
                                        </span>
                                        <Badge variant="secondary">
                                            {order.coupon_code}
                                        </Badge>
                                    </div>
                                )}
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
                                <div className="flex items-center justify-between font-medium">
                                    <span>Total</span>
                                    <span className="tabular-nums">
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
                            {order.buyer_notes && (
                                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                                    <p className="mb-1 text-xs text-muted-foreground">
                                        Catatan pembeli
                                    </p>
                                    <p>{order.buyer_notes}</p>
                                </div>
                            )}
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
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                                <span>
                                                    Dibayar:{' '}
                                                    {formatDate(
                                                        payment.paid_at,
                                                    )}
                                                </span>
                                                {payment.midtrans_order_id && (
                                                    <span className="font-mono">
                                                        Midtrans:{' '}
                                                        {
                                                            payment.midtrans_order_id
                                                        }
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        {/* History */}
                        <Card className="gap-3 p-4">
                            <h2 className="font-medium">Riwayat Status</h2>
                            <div className="flex flex-col gap-3">
                                {order.histories.map((h, i) => (
                                    <div key={h.id} className="flex gap-3">
                                        <div className="flex flex-col items-center">
                                            <div
                                                className={`size-2.5 rounded-full ${i === 0 ? 'bg-primary' : 'bg-muted-foreground/30'}`}
                                            />
                                            {i < order.histories.length - 1 && (
                                                <div className="w-px flex-1 bg-muted" />
                                            )}
                                        </div>
                                        <div className="flex flex-col gap-0.5 pb-2">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium">
                                                    {STATUS_LABELS[h.status] ??
                                                        h.status}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDate(h.created_at)}
                                                </span>
                                            </div>
                                            {h.note && (
                                                <p className="text-xs text-muted-foreground">
                                                    {h.note}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Shipping */}
                        {address && (
                            <Card className="gap-3 p-4">
                                <h2 className="font-medium">Pengiriman</h2>
                                <div
                                    data-test="order-address"
                                    className="flex flex-col gap-1 text-sm"
                                >
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary">
                                            {address.label}
                                        </Badge>
                                        <span className="font-medium">
                                            {address.recipient_name}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {address.full_address}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {address.province}, {address.city},{' '}
                                        {address.district},{' '}
                                        {address.subdistrict}{' '}
                                        {address.postal_code}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {address.phone}
                                    </p>
                                </div>

                                {order.resi_number && (
                                    <div className="flex flex-col gap-1 rounded-lg bg-muted/50 p-3 text-sm">
                                        <div className="flex items-center gap-2">
                                            <span className="text-muted-foreground">
                                                Resi:
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
                                    </div>
                                )}

                                {order.status === 'lunas' &&
                                    !order.resi_number && (
                                        <div className="flex flex-col gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-fit gap-1.5"
                                                data-test="courier-rates-button"
                                                disabled={rating}
                                                onClick={handleGetCourierRates}
                                            >
                                                {rating && <Spinner />}
                                                Hitung Ongkir
                                            </Button>

                                            {courier_rates &&
                                                courier_rates.length > 0 && (
                                                    <div className="flex flex-col gap-2">
                                                        {courier_rates.map(
                                                            (rate) => {
                                                                const isSelected =
                                                                    selectedRate?.courier_code ===
                                                                        rate.courier_code &&
                                                                    selectedRate?.courier_service_code ===
                                                                        rate.courier_service_code;
                                                                const value = `${rate.courier_code}|${rate.courier_service_code}`;

                                                                return (
                                                                    <label
                                                                        key={
                                                                            value
                                                                        }
                                                                        htmlFor={`admin-courier-${value}`}
                                                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
                                                                            isSelected
                                                                                ? 'border-primary bg-primary/5'
                                                                                : 'hover:bg-muted/50'
                                                                        }`}
                                                                    >
                                                                        <input
                                                                            id={`admin-courier-${value}`}
                                                                            type="radio"
                                                                            name="admin_courier_radio"
                                                                            value={
                                                                                value
                                                                            }
                                                                            checked={
                                                                                isSelected
                                                                            }
                                                                            onChange={() =>
                                                                                setSelectedRate(
                                                                                    rate,
                                                                                )
                                                                            }
                                                                            data-test={`admin-courier-select-${rate.courier_code}-${rate.courier_service_code}`}
                                                                            className="mt-1 size-4 accent-primary"
                                                                        />
                                                                        <div className="flex flex-1 flex-col gap-0.5 text-sm">
                                                                            <span className="font-medium">
                                                                                {rate.courier_code.toUpperCase()}{' '}
                                                                                {
                                                                                    rate.courier_service_code
                                                                                }
                                                                            </span>
                                                                            <span className="text-muted-foreground">
                                                                                {
                                                                                    rate.service_type
                                                                                }{' '}
                                                                                ·{' '}
                                                                                {
                                                                                    rate.duration
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                        <span className="font-medium tabular-nums">
                                                                            {formatPrice(
                                                                                String(
                                                                                    rate.price,
                                                                                ),
                                                                            )}
                                                                        </span>
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                        <Button
                                                            type="button"
                                                            className="w-fit gap-1.5"
                                                            data-test="book-courier-button"
                                                            disabled={
                                                                !selectedRate ||
                                                                booking
                                                            }
                                                            onClick={
                                                                handleBookCourier
                                                            }
                                                        >
                                                            {booking && (
                                                                <Spinner />
                                                            )}
                                                            Booking Kurir
                                                        </Button>
                                                    </div>
                                                )}
                                        </div>
                                    )}
                            </Card>
                        )}
                    </div>

                    {/* Right sticky */}
                    <div className="lg:sticky lg:top-6 lg:self-start">
                        <Card className="gap-4 p-4">
                            <h2 className="font-medium">Ubah Status</h2>
                            {can_pay_remaining && (
                                <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                    Sisa pelunasan:{' '}
                                    <span className="font-medium tabular-nums">
                                        {formatPrice(remaining_amount)}
                                    </span>
                                </div>
                            )}
                            <div className="flex flex-col gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="transition-status">
                                        Status berikutnya
                                    </Label>
                                    <Select
                                        value={nextStatus}
                                        onValueChange={setNextStatus}
                                    >
                                        <SelectTrigger
                                            id="transition-status"
                                            data-test="transition-select"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Pilih status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((s) => (
                                                <SelectItem
                                                    key={s.value}
                                                    value={s.value}
                                                >
                                                    {s.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="transition-note">
                                        Catatan (opsional)
                                    </Label>
                                    <Input
                                        id="transition-note"
                                        data-test="transition-note"
                                        value={note}
                                        onChange={(e) =>
                                            setNote(e.target.value)
                                        }
                                        placeholder="Catatan transisi..."
                                    />
                                </div>
                                <Button
                                    type="button"
                                    className="w-full"
                                    data-test="transition-submit-button"
                                    disabled={saving || !nextStatus}
                                    onClick={handleTransition}
                                >
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

AdminOrdersShow.layout = {
    breadcrumbs: [
        { title: 'Pesanan', href: adminOrdersIndex.url() },
        { title: 'Detail', href: adminOrdersIndex.url() },
    ],
};
