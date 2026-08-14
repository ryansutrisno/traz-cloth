import { Head, Link, router } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { destroy, update } from '@/routes/cart';
import { index as checkoutIndex } from '@/routes/checkout';
import { index as campaignsIndex, show } from '@/routes/campaigns';

type CartItem = {
    id: number;
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
    items: CartItem[];
    total: number;
    empty: boolean;
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return rupiah.format(value);
}

export default function CartIndex({ items, total, empty }: Props) {
    const [pendingId, setPendingId] = useState<number | null>(null);

    const changeQty = (item: CartItem, nextQty: number) => {
        if (nextQty < 1) {
            return;
        }

        setPendingId(item.id);
        router.patch(
            update.url({ id: item.id }),
            { qty: nextQty },
            {
                preserveScroll: true,
                onSuccess: () => setPendingId(null),
                onError: () => setPendingId(null),
            },
        );
    };

    const removeItem = (item: CartItem) => {
        if (!window.confirm('Hapus item ini dari keranjang?')) {
            return;
        }

        router.delete(destroy.url({ id: item.id }), { preserveScroll: true });
    };

    if (empty) {
        return (
            <>
                <Head title="Keranjang" />
                <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 py-20 text-center">
                    <p className="text-lg font-medium">
                        Keranjang masih kosong
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Yuk pilih desain kaos pre-order favorit kamu.
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
            <Head title="Keranjang" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Keranjang
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Periksa pesanan kamu sebelum checkout
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    {/* Items */}
                    <div className="flex flex-col gap-3">
                        {items.map((item) => (
                            <Card
                                key={item.id}
                                data-test="cart-item"
                                className="gap-0 p-4"
                            >
                                <div className="flex gap-4">
                                    {/* Image */}
                                    <div className="size-20 shrink-0 overflow-hidden rounded-lg bg-muted">
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt={item.title}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-xl font-semibold text-muted-foreground/40">
                                                {item.title.charAt(0)}
                                            </div>
                                        )}
                                    </div>

                                    {/* Info */}
                                    <div className="flex flex-1 flex-col gap-1">
                                        <Link
                                            href={show.url({ slug: item.slug })}
                                            className="font-medium hover:text-primary"
                                        >
                                            {item.title}
                                        </Link>
                                        <Badge
                                            variant="secondary"
                                            className="w-fit"
                                        >
                                            {item.size}
                                        </Badge>
                                        <p className="text-sm text-muted-foreground">
                                            {formatPrice(item.unit_price)} / pcs
                                        </p>
                                    </div>

                                    {/* Qty + subtotal */}
                                    <div className="flex flex-col items-end justify-between gap-2">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-8"
                                                disabled={
                                                    pendingId === item.id ||
                                                    item.qty <= 1
                                                }
                                                onClick={() =>
                                                    changeQty(
                                                        item,
                                                        item.qty - 1,
                                                    )
                                                }
                                                aria-label="Kurangi jumlah"
                                            >
                                                <Minus className="size-4" />
                                            </Button>
                                            <Input
                                                data-test="cart-item-qty"
                                                readOnly
                                                value={item.qty}
                                                className="h-8 w-12 text-center"
                                                aria-label="Jumlah"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-8"
                                                disabled={pendingId === item.id}
                                                onClick={() =>
                                                    changeQty(
                                                        item,
                                                        item.qty + 1,
                                                    )
                                                }
                                                aria-label="Tambah jumlah"
                                            >
                                                <Plus className="size-4" />
                                            </Button>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(item.subtotal)}
                                            </span>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                data-test="remove-cart-item"
                                                onClick={() => removeItem(item)}
                                                aria-label="Hapus item"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>

                    {/* Summary */}
                    <div className="lg:sticky lg:top-6 lg:self-start">
                        <Card className="gap-4 p-4">
                            <h2 className="font-medium">Ringkasan Pesanan</h2>
                            <div className="flex flex-col gap-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Subtotal
                                    </span>
                                    <span
                                        data-test="cart-total"
                                        className="font-medium tabular-nums"
                                    >
                                        {formatPrice(total)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Ongkir
                                    </span>
                                    <span className="text-muted-foreground">
                                        Dihitung saat pelunasan
                                    </span>
                                </div>
                            </div>
                            <div className="border-t pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Total</span>
                                    <span className="text-lg font-semibold tabular-nums">
                                        {formatPrice(total)}
                                    </span>
                                </div>
                            </div>
                            <Button
                                asChild
                                className="w-full"
                                data-test="checkout-button"
                            >
                                <Link href={checkoutIndex.url()}>
                                    Lanjut ke Checkout
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={campaignsIndex.url()}>
                                    Tambah Pesanan
                                </Link>
                            </Button>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

CartIndex.layout = {
    title: 'Keranjang',
    description: 'Periksa pesanan kamu sebelum checkout',
};
