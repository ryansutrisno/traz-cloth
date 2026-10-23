import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index as addressesIndex } from '@/routes/addresses';
import { rates, store } from '@/routes/checkout';
import {
    apply as applyCoupon,
    remove as removeCoupon,
} from '@/routes/checkout/coupon';
import { index as campaignsIndex } from '@/routes/campaigns';

type CheckoutItem = {
    campaign_id: number;
    slug: string;
    title: string;
    image_url: string | null;
    size: string;
    qty: number;
    unit_price: number;
    subtotal: number;
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
    is_default: boolean;
};

type CourierRate = {
    courier_code: string;
    courier_service_code: string;
    service_type: string;
    price: number;
    duration: string;
    shipment_duration_range: string;
};

type Props = {
    items: CheckoutItem[];
    subtotal: number;
    dp_amount: number;
    user: { name: string; email: string; phone: string | null };
    payment_modes: Array<'dp' | 'lunas'>;
    addresses: Address[];
    courier_rates: CourierRate[] | null;
    selected_address_id: number | null;
    discount_amount: number;
    coupon: {
        code: string;
        description: string | null;
        type: 'percentage' | 'fixed';
        type_label: string;
        value: number;
    } | null;
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return rupiah.format(value);
}

const STEPS = ['Keranjang', 'Alamat & Kurir', 'Pembayaran', 'Selesai'] as const;
const ACTIVE_STEP = 2; // 'Pembayaran'

export default function CheckoutIndex({
    items,
    subtotal,
    user,
    payment_modes,
    addresses,
    courier_rates,
    selected_address_id,
    discount_amount,
    coupon,
}: Props) {
    const [paymentMode, setPaymentMode] = useState<'dp' | 'lunas'>(
        payment_modes[0] ?? 'dp',
    );
    const [selectedAddressId, setSelectedAddressId] = useState<number | null>(
        selected_address_id,
    );
    const [selectedRate, setSelectedRate] = useState<CourierRate | null>(null);
    const [rating, setRating] = useState(false);
    const [removingCoupon, setRemovingCoupon] = useState(false);

    const handleGetRates = () => {
        if (!selectedAddressId) {
            toast.error('Pilih alamat dulu');

            return;
        }

        setRating(true);
        router.post(
            rates.url(),
            { address_id: selectedAddressId },
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

    const shippingCost = selectedRate?.price ?? 0;
    const totalWithShipping = subtotal - discount_amount + shippingCost;
    const payNow =
        paymentMode === 'dp'
            ? Math.round(totalWithShipping * 0.5)
            : totalWithShipping;

    const canSubmit = !!selectedAddressId && !!selectedRate && !!paymentMode;

    const submitLabel =
        paymentMode === 'dp'
            ? `Bayar DP ${formatPrice(payNow)}`
            : `Bayar Lunas ${formatPrice(payNow)}`;

    return (
        <>
            <Head title="Checkout" />
            <div className="flex flex-1 flex-col gap-4 md:gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                        Checkout
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pilih cara bayar dan konfirmasi pesanan
                    </p>
                </div>

                {/* Progress stepper — murni visual */}
                <ol className="flex items-center gap-1 text-xs sm:gap-2">
                    {STEPS.map((step, i) => {
                        const isDone = i < ACTIVE_STEP;
                        const isActive = i === ACTIVE_STEP;

                        return (
                            <li
                                key={step}
                                className="flex flex-1 items-center gap-1 sm:gap-2"
                            >
                                <span
                                    className={`flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-medium sm:size-7 sm:text-xs ${
                                        isDone
                                            ? 'bg-primary text-primary-foreground'
                                            : isActive
                                              ? 'border-2 border-primary bg-primary/10 text-primary'
                                              : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {isDone ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        i + 1
                                    )}
                                </span>
                                <span
                                    className={`hidden truncate sm:inline ${
                                        isActive
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {step}
                                </span>
                                {i < STEPS.length - 1 && (
                                    <span
                                        className={`h-px flex-1 ${
                                            i < ACTIVE_STEP
                                                ? 'bg-primary'
                                                : 'bg-border'
                                        }`}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ol>

                <Form
                    {...applyCoupon.form()}
                    disableWhileProcessing
                    data-test="coupon-form"
                    className="flex flex-col gap-3"
                >
                    {({ processing, errors }) => (
                        <Card className="gap-3 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-medium">Kupon</h2>
                                {coupon && <Badge>{coupon.code}</Badge>}
                            </div>
                            {coupon ? (
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-emerald-50 p-3 text-sm dark:bg-emerald-950/30">
                                    <div className="flex flex-col gap-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {coupon.type_label}
                                            </Badge>
                                            <span className="font-medium">
                                                {coupon.code}
                                            </span>
                                        </div>
                                        {coupon.description && (
                                            <span className="text-muted-foreground">
                                                {coupon.description}
                                            </span>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        data-test="remove-coupon-button"
                                        disabled={removingCoupon}
                                        onClick={() => {
                                            setRemovingCoupon(true);
                                            router.delete(removeCoupon.url(), {
                                                preserveScroll: true,
                                                onFinish: () =>
                                                    setRemovingCoupon(false),
                                            });
                                        }}
                                    >
                                        {removingCoupon && <Spinner />}
                                        Hapus
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        name="code"
                                        placeholder="Masukkan kode kupon"
                                        className="uppercase"
                                        data-test="coupon-code-input"
                                    />
                                    <Button
                                        type="submit"
                                        className="gap-1.5 sm:shrink-0"
                                        data-test="apply-coupon-button"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Pakai Kupon
                                    </Button>
                                </div>
                            )}
                            <InputError message={errors.coupon} />
                        </Card>
                    )}
                </Form>

                <Form
                    {...store.form()}
                    disableWhileProcessing
                    className="flex flex-col gap-4 md:gap-6 lg:grid lg:grid-cols-[1fr_360px]"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="payment_mode"
                                value={paymentMode}
                            />
                            <input
                                type="hidden"
                                name="address_id"
                                value={selectedAddressId ?? ''}
                            />
                            <input
                                type="hidden"
                                name="courier_code"
                                value={selectedRate?.courier_code ?? ''}
                            />
                            <input
                                type="hidden"
                                name="courier_service"
                                value={selectedRate?.courier_service_code ?? ''}
                            />
                            <input
                                type="hidden"
                                name="shipping_cost"
                                value={shippingCost}
                            />

                            <div className="flex flex-col gap-6">
                                {/* Address */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Alamat Pengiriman
                                    </h2>
                                    {addresses.length === 0 ? (
                                        <Alert>
                                            <AlertTitle>
                                                Belum ada alamat
                                            </AlertTitle>
                                            <AlertDescription>
                                                <Link
                                                    href={addressesIndex.url()}
                                                    className="font-medium underline"
                                                >
                                                    Tambahkan alamat dulu
                                                </Link>{' '}
                                                untuk menghitung ongkir.
                                            </AlertDescription>
                                        </Alert>
                                    ) : (
                                        <div className="flex flex-col gap-2">
                                            {addresses.map((address) => {
                                                const isSelected =
                                                    selectedAddressId ===
                                                    address.id;

                                                return (
                                                    <label
                                                        key={address.id}
                                                        htmlFor={`address-${address.id}`}
                                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
                                                            isSelected
                                                                ? 'border-primary bg-primary/5'
                                                                : 'hover:bg-muted/50'
                                                        }`}
                                                    >
                                                        <input
                                                            id={`address-${address.id}`}
                                                            type="radio"
                                                            name="address_radio"
                                                            value={address.id}
                                                            checked={isSelected}
                                                            onChange={() => {
                                                                setSelectedAddressId(
                                                                    address.id,
                                                                );
                                                                setSelectedRate(
                                                                    null,
                                                                );
                                                            }}
                                                            data-test={`address-select-${address.id}`}
                                                            className="mt-1 size-4 accent-primary"
                                                        />
                                                        <div className="flex flex-1 flex-col gap-0.5 text-sm">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        address.recipient_name
                                                                    }
                                                                </span>
                                                                <Badge variant="secondary">
                                                                    {
                                                                        address.label
                                                                    }
                                                                </Badge>
                                                                {address.is_default && (
                                                                    <Badge>
                                                                        Utama
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <span className="text-muted-foreground">
                                                                {
                                                                    address.full_address
                                                                }
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    address.province
                                                                }
                                                                , {address.city}
                                                                ,{' '}
                                                                {
                                                                    address.district
                                                                }{' '}
                                                                {
                                                                    address.postal_code
                                                                }
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {address.phone}
                                                            </span>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-fit gap-1.5"
                                                data-test="rates-button"
                                                disabled={
                                                    !selectedAddressId || rating
                                                }
                                                onClick={handleGetRates}
                                            >
                                                {rating && <Spinner />}
                                                Hitung Ongkir
                                            </Button>
                                        </div>
                                    )}
                                    <InputError message={errors.address_id} />
                                </Card>

                                {/* Courier */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Kurir & Ongkir
                                    </h2>
                                    {courier_rates === null ? (
                                        <p className="text-sm text-muted-foreground">
                                            Pilih alamat lalu hitung ongkir.
                                        </p>
                                    ) : courier_rates.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Tidak ada layanan kurir tersedia
                                            untuk alamat ini.
                                        </p>
                                    ) : (
                                        <div className="flex flex-col gap-2">
                                            {courier_rates.map((rate) => {
                                                const isSelected =
                                                    selectedRate?.courier_code ===
                                                        rate.courier_code &&
                                                    selectedRate?.courier_service_code ===
                                                        rate.courier_service_code;
                                                const value = `${rate.courier_code}|${rate.courier_service_code}`;

                                                return (
                                                    <label
                                                        key={value}
                                                        htmlFor={`courier-${value}`}
                                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
                                                            isSelected
                                                                ? 'border-primary bg-primary/5'
                                                                : 'hover:bg-muted/50'
                                                        }`}
                                                    >
                                                        <input
                                                            id={`courier-${value}`}
                                                            type="radio"
                                                            name="courier_radio"
                                                            value={value}
                                                            checked={isSelected}
                                                            onChange={() =>
                                                                setSelectedRate(
                                                                    rate,
                                                                )
                                                            }
                                                            data-test={`courier-select-${rate.courier_code}-${rate.courier_service_code}`}
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
                                                                {rate.duration}
                                                            </span>
                                                        </div>
                                                        <span className="font-medium tabular-nums">
                                                            {formatPrice(
                                                                rate.price,
                                                            )}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <InputError message={errors.courier_code} />
                                </Card>

                                {/* Buyer info */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Data Pemesan
                                    </h2>
                                    <div className="grid gap-1 text-sm">
                                        <p>
                                            <span className="text-muted-foreground">
                                                Nama:{' '}
                                            </span>
                                            {user.name}
                                        </p>
                                        <p>
                                            <span className="text-muted-foreground">
                                                Email:{' '}
                                            </span>
                                            {user.email}
                                        </p>
                                        <p>
                                            <span className="text-muted-foreground">
                                                WhatsApp:{' '}
                                            </span>
                                            {user.phone ?? '-'}
                                        </p>
                                    </div>
                                </Card>

                                {/* Payment mode — kartu pilihan menonjol */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Cara Pembayaran
                                    </h2>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {payment_modes.map((mode) => {
                                            const isSelected =
                                                paymentMode === mode;
                                            const isDp = mode === 'dp';
                                            const amount = isDp
                                                ? payNow
                                                : totalWithShipping;

                                            return (
                                                <label
                                                    key={mode}
                                                    htmlFor={`mode-${mode}`}
                                                    className={`flex cursor-pointer flex-col gap-2 rounded-lg border-2 p-4 transition-all ${
                                                        isSelected
                                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                            : 'border-border hover:border-muted-foreground/30'
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="flex flex-col gap-0.5">
                                                            <span className="text-base font-semibold">
                                                                {isDp
                                                                    ? 'DP 50%'
                                                                    : 'Bayar Lunas'}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {isDp
                                                                    ? 'Lunasi setelah barang siap'
                                                                    : 'Langsung diproses cetak'}
                                                            </span>
                                                        </div>
                                                        <input
                                                            id={`mode-${mode}`}
                                                            type="radio"
                                                            name="payment_mode_radio"
                                                            value={mode}
                                                            checked={isSelected}
                                                            onChange={() =>
                                                                setPaymentMode(
                                                                    mode,
                                                                )
                                                            }
                                                            className="size-4 accent-primary"
                                                        />
                                                    </div>
                                                    <span className="text-lg font-bold tabular-nums">
                                                        {formatPrice(amount)}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        Desain kamu diproses cetak &amp; dikirim
                                        setelah pembayaran dikonfirmasi.
                                    </p>

                                    {paymentMode === 'dp' && (
                                        <div className="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                                            <p>
                                                DP hangus total jika order
                                                dibatalkan.
                                            </p>
                                        </div>
                                    )}
                                    <InputError message={errors.payment_mode} />
                                </Card>

                                {/* Items */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Pesanan ({items.length} item)
                                    </h2>
                                    <div className="flex flex-col divide-y">
                                        {items.map((item) => (
                                            <div
                                                key={`${item.campaign_id}-${item.size}`}
                                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <div className="size-12 shrink-0 overflow-hidden rounded-md bg-muted">
                                                    {item.image_url ? (
                                                        <img
                                                            src={item.image_url}
                                                            alt=""
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-full items-center justify-center text-sm font-semibold text-muted-foreground/40">
                                                            {item.title.charAt(
                                                                0,
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex flex-1 flex-col">
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
                                                    {formatPrice(item.subtotal)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            </div>

                            {/* Summary */}
                            <div className="lg:sticky lg:top-6 lg:self-start">
                                <Card className="gap-4 p-4">
                                    <h2 className="font-medium">
                                        Ringkasan Pembayaran
                                    </h2>
                                    <div className="flex flex-col gap-2 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                Subtotal pesanan
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(subtotal)}
                                            </span>
                                        </div>
                                        {discount_amount > 0 && (
                                            <div className="flex items-center justify-between text-emerald-600">
                                                <span>Diskon</span>
                                                <span className="tabular-nums">
                                                    -
                                                    {formatPrice(
                                                        discount_amount,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                Ongkir
                                            </span>
                                            <span className="tabular-nums">
                                                {selectedRate
                                                    ? formatPrice(shippingCost)
                                                    : '—'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                Total
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(totalWithShipping)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                Metode
                                            </span>
                                            <span className="font-medium">
                                                {paymentMode === 'dp'
                                                    ? 'DP 50%'
                                                    : 'Lunas'}
                                            </span>
                                        </div>
                                        {paymentMode === 'dp' && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground">
                                                    Sisa pelunasan
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {formatPrice(
                                                        totalWithShipping -
                                                            payNow,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <div className="border-t pt-3">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">
                                                Bayar sekarang
                                            </span>
                                            <span className="text-lg font-semibold tabular-nums">
                                                {formatPrice(payNow)}
                                            </span>
                                        </div>
                                        {paymentMode === 'dp' && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                DP 50% termasuk ongkir
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full gap-1.5"
                                        data-test="checkout-submit-button"
                                        disabled={processing || !canSubmit}
                                    >
                                        {processing && <Spinner />}
                                        {submitLabel}
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={campaignsIndex.url()}>
                                            Kembali Belanja
                                        </Link>
                                    </Button>
                                </Card>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CheckoutIndex.layout = {
    title: 'Checkout',
    description: 'Pilih cara bayar dan konfirmasi pesanan',
};
