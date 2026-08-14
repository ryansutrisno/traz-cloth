import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as campaignsIndex } from '@/routes/campaigns';

type Order = {
    order_number: string;
    status: string;
    payment_mode: 'dp' | 'lunas';
    total_amount: number;
    dp_amount: number | null;
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

export default function CheckoutSuccess({ order }: Props) {
    const paidNow =
        order.payment_mode === 'dp' && order.dp_amount !== null
            ? order.dp_amount
            : order.total_amount;

    return (
        <>
            <Head title="Pembayaran Berhasil" />
            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-4 md:p-6">
                <div className="w-full max-w-md">
                    <Card className="gap-5 p-6 text-center">
                        <div className="flex flex-col items-center gap-3">
                            <div className="flex size-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-950/40">
                                <CheckCircle2 className="size-9 text-green-600 dark:text-green-400" />
                            </div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Pesanan berhasil dibuat!
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Terima kasih sudah memesan. Kami sudah kirim
                                detail pesanan ke email kamu.
                            </p>
                        </div>

                        {/* Order details */}
                        <div className="flex flex-col gap-3 rounded-lg border p-4 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Nomor pesanan
                                </span>
                                <span
                                    data-test="order-number"
                                    className="font-mono font-medium"
                                >
                                    {order.order_number}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Status
                                </span>
                                <Badge variant="secondary">
                                    {order.status}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Mode bayar
                                </span>
                                <span className="font-medium">
                                    {order.payment_mode === 'dp'
                                        ? 'DP 50%'
                                        : 'Lunas'}
                                </span>
                            </div>
                            <div className="border-t pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Dibayar</span>
                                    <span className="font-semibold tabular-nums">
                                        {formatPrice(paidNow)}
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
                                            belum dibayar
                                        </p>
                                    )}
                            </div>
                        </div>

                        {/* Info based on mode */}
                        <div className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                            {order.payment_mode === 'dp' ? (
                                <p>
                                    Sisa pelunasan akan dikonfirmasi saat barang
                                    siap kirim. <strong>DP hangus</strong> jika
                                    batal.
                                </p>
                            ) : (
                                <p>Pesanan masuk ke produksi.</p>
                            )}
                        </div>

                        <Button asChild className="w-full">
                            <Link href={campaignsIndex.url()}>
                                Lanjut Belanja
                            </Link>
                        </Button>
                    </Card>
                </div>
            </div>
        </>
    );
}

CheckoutSuccess.layout = {
    title: 'Pembayaran Berhasil',
    description: 'Pesanan kamu berhasil dibuat',
};
