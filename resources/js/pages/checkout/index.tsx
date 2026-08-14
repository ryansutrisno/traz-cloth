import { Form, Head, Link } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/checkout';
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

type Props = {
    items: CheckoutItem[];
    subtotal: number;
    dp_amount: number;
    user: { name: string; email: string; phone: string | null };
    payment_modes: Array<'dp' | 'lunas'>;
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return rupiah.format(value);
}

export default function CheckoutIndex({
    items,
    subtotal,
    dp_amount,
    user,
    payment_modes,
}: Props) {
    const [paymentMode, setPaymentMode] = useState<'dp' | 'lunas'>(
        payment_modes[0] ?? 'dp',
    );

    const dueNow = paymentMode === 'dp' ? dp_amount : subtotal;

    return (
        <>
            <Head title="Checkout" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Checkout
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pilih cara bayar dan konfirmasi pesanan
                    </p>
                </div>

                <Form
                    {...store.form()}
                    disableWhileProcessing
                    className="grid gap-6 lg:grid-cols-[1fr_360px]"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="payment_mode"
                                value={paymentMode}
                            />

                            <div className="flex flex-col gap-6">
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

                                {/* Payment mode */}
                                <Card className="gap-3 p-4">
                                    <h2 className="font-medium">
                                        Cara Pembayaran
                                    </h2>
                                    <div className="flex flex-col gap-2">
                                        {payment_modes.map((mode) => {
                                            const isSelected =
                                                paymentMode === mode;
                                            const amount =
                                                mode === 'dp'
                                                    ? dp_amount
                                                    : subtotal;

                                            return (
                                                <label
                                                    key={mode}
                                                    htmlFor={`mode-${mode}`}
                                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
                                                        isSelected
                                                            ? 'border-primary bg-primary/5'
                                                            : 'hover:bg-muted/50'
                                                    }`}
                                                >
                                                    <input
                                                        id={`mode-${mode}`}
                                                        type="radio"
                                                        name="payment_mode_radio"
                                                        value={mode}
                                                        checked={isSelected}
                                                        onChange={() =>
                                                            setPaymentMode(mode)
                                                        }
                                                        className="mt-1 size-4 accent-primary"
                                                    />
                                                    <div className="flex flex-1 flex-col gap-0.5">
                                                        <span className="font-medium">
                                                            {mode === 'dp'
                                                                ? 'DP 50% dulu'
                                                                : 'Langsung lunas'}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            Bayar{' '}
                                                            {formatPrice(
                                                                amount,
                                                            )}{' '}
                                                            {mode === 'dp'
                                                                ? '(sisa dilunasi setelah barang siap)'
                                                                : '(lunas penuh)'}
                                                        </span>
                                                    </div>
                                                    <span className="font-medium tabular-nums">
                                                        {formatPrice(amount)}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>

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
                                                        subtotal - dp_amount,
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
                                                {formatPrice(dueNow)}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full gap-1.5"
                                        data-test="checkout-submit-button"
                                    >
                                        {processing && <Spinner />}
                                        Bayar Sekarang
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
