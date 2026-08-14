import { Head, router } from '@inertiajs/react';
import { FlaskConical, Info } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { pay as devPay } from '@/routes/checkout/dev';

type Order = {
    order_number: string;
    payment_mode: 'dp' | 'lunas';
    total_amount: number;
    dp_amount: number | null;
    status: string;
};

type Props = {
    order: Order;
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return rupiah.format(value);
}

export default function CheckoutDev({ order }: Props) {
    const [processing, setProcessing] = useState(false);

    const dueNow =
        order.payment_mode === 'dp' && order.dp_amount !== null
            ? order.dp_amount
            : order.total_amount;

    const handlePay = () => {
        setProcessing(true);
        router.post(
            devPay.url(order.order_number as unknown as number),
            {},
            {
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Simulasi Pembayaran" />
            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-4 md:p-6">
                <div className="w-full max-w-md">
                    {/* Dev banner */}
                    <div className="mb-4 flex items-center justify-center gap-2 rounded-lg border border-dashed border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        <FlaskConical className="size-4 shrink-0" />
                        <span className="font-medium">
                            Mode Pengembangan — Simulasi Pembayaran
                        </span>
                    </div>

                    <Card className="gap-4 p-6">
                        <div className="flex flex-col gap-1">
                            <h1 className="text-xl font-semibold">
                                Pembayaran Pesanan
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Tinjau detail lalu tekan tombol bayar untuk
                                mensimulasikan pembayaran.
                            </p>
                        </div>

                        {/* Order details */}
                        <div className="flex flex-col gap-3 rounded-lg border p-4 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Nomor pesanan
                                </span>
                                <span className="font-mono font-medium">
                                    {order.order_number}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Mode bayar
                                </span>
                                <Badge
                                    variant={
                                        order.payment_mode === 'dp'
                                            ? 'secondary'
                                            : 'default'
                                    }
                                >
                                    {order.payment_mode === 'dp'
                                        ? 'DP 50%'
                                        : 'Lunas'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Status
                                </span>
                                <span className="font-medium">
                                    {order.status}
                                </span>
                            </div>
                            <div className="border-t pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        Jumlah bayar
                                    </span>
                                    <span className="text-lg font-semibold tabular-nums">
                                        {formatPrice(dueNow)}
                                    </span>
                                </div>
                                {order.payment_mode === 'dp' &&
                                    order.dp_amount !== null && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Sisa{' '}
                                            {formatPrice(
                                                order.total_amount -
                                                    order.dp_amount,
                                            )}{' '}
                                            dilunasi setelah barang siap.
                                        </p>
                                    )}
                            </div>
                        </div>

                        <Button
                            type="button"
                            className="w-full gap-1.5"
                            data-test="dev-pay-button"
                            disabled={processing}
                            onClick={handlePay}
                        >
                            {processing && <Spinner />}
                            Bayar (Simulasi)
                        </Button>

                        <div className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Info className="mt-0.5 size-3.5 shrink-0" />
                            <p>
                                Di produksi kamu akan diarahkan ke halaman
                                pembayaran Midtrans.
                            </p>
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}

CheckoutDev.layout = {
    title: 'Simulasi Pembayaran',
    description: 'Mode pengembangan',
};
