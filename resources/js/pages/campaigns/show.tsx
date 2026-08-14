import { Head, Link, router } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { add } from '@/routes/cart';
import { index } from '@/routes/campaigns';

type Campaign = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    image_url: string | null;
    base_price: number;
    quota: number;
    sold_count: number;
    status: string;
    ready_stock_enabled: boolean;
};

type PriceBySize = {
    price: number;
    price_breakdown: unknown;
    stock_ready: number;
};

type Props = {
    campaign: Campaign;
    price_by_size: Record<string, PriceBySize>;
    available: number;
    is_sold_out: boolean;
    progress_percent: number;
};

const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'] as const;

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return rupiah.format(value);
}

export default function CampaignsShow({
    campaign,
    price_by_size,
    available,
    is_sold_out,
    progress_percent,
}: Props) {
    const sizes = Object.keys(price_by_size).sort(
        (a, b) =>
            SIZE_ORDER.indexOf(a as (typeof SIZE_ORDER)[number]) -
            SIZE_ORDER.indexOf(b as (typeof SIZE_ORDER)[number]),
    );

    const [selectedSize, setSelectedSize] = useState('');
    const [qty, setQty] = useState(1);
    const [adding, setAdding] = useState(false);

    const canBuy = available > 0 && !is_sold_out;

    const selectedPrice =
        selectedSize !== '' && price_by_size[selectedSize]
            ? price_by_size[selectedSize].price
            : null;

    const handleAddToCart = () => {
        if (!selectedSize) {
            toast.error('Pilih ukuran dulu');

            return;
        }

        setAdding(true);
        router.post(
            add.url(),
            {
                campaign_id: campaign.id,
                size: selectedSize,
                qty,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAdding(false);
                    toast.success('Ditambahkan ke keranjang');
                },
                onError: (errors) => {
                    setAdding(false);
                    const msg =
                        (errors.size as string | undefined) ||
                        (errors.qty as string | undefined) ||
                        'Gagal menambahkan ke keranjang';
                    toast.error(`Gagal: ${msg}`);
                },
            },
        );
    };

    return (
        <>
            <Head title={campaign.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Breadcrumb */}
                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={index.url()} className="gap-1">
                            <span aria-hidden>←</span> Semua kampanye
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-8 lg:grid-cols-2">
                    {/* Image */}
                    <div className="overflow-hidden rounded-xl border bg-muted">
                        <div className="relative aspect-[4/5] w-full">
                            {campaign.image_url ? (
                                <img
                                    src={campaign.image_url}
                                    alt={campaign.title}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center bg-gradient-to-br from-muted to-muted-foreground/10">
                                    <span className="text-5xl font-semibold text-muted-foreground/40">
                                        {campaign.title.charAt(0)}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Info */}
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <div className="flex items-start justify-between gap-3">
                                <h1
                                    data-test="campaign-title"
                                    className="text-2xl font-semibold tracking-tight md:text-3xl"
                                >
                                    {campaign.title}
                                </h1>
                                {is_sold_out && (
                                    <Badge variant="destructive">
                                        Sold Out
                                    </Badge>
                                )}
                            </div>
                        </div>

                        {/* Description */}
                        {campaign.description && (
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {campaign.description}
                            </p>
                        )}

                        {/* Sold out alert */}
                        {is_sold_out && (
                            <Alert variant="destructive">
                                <AlertTitle>Slot habis</AlertTitle>
                                <AlertDescription>
                                    Kampanye ini sudah sold out. Slot pre-order
                                    tidak tersedia lagi.
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Quota progress */}
                        <Card className="gap-3 p-4">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">
                                    Kuota pre-order
                                </span>
                                <span className="text-muted-foreground">
                                    {campaign.sold_count} / {campaign.quota}{' '}
                                    terisi
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={`h-full rounded-full transition-all ${is_sold_out ? 'bg-destructive' : 'bg-primary'}`}
                                    style={{
                                        width: `${Math.min(100, Math.max(0, progress_percent))}%`,
                                    }}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {is_sold_out
                                    ? 'Slot habis'
                                    : `Sisa ${available} slot`}
                            </p>
                        </Card>

                        {/* Size table */}
                        <div className="flex flex-col gap-3">
                            <h2 className="text-lg font-medium">
                                Pilih ukuran
                            </h2>
                            <div className="overflow-hidden rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-2.5 font-medium">
                                                Ukuran
                                            </th>
                                            <th className="px-4 py-2.5 text-right font-medium">
                                                Harga
                                            </th>
                                            <th className="px-4 py-2.5 text-center font-medium">
                                                Ready Stock
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {sizes.map((size) => {
                                            const data = price_by_size[size];

                                            return (
                                                <tr
                                                    key={size}
                                                    data-test="size-row"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {size}
                                                    </td>
                                                    <td
                                                        data-test="size-price"
                                                        className="px-4 py-3 text-right tabular-nums"
                                                    >
                                                        {formatPrice(
                                                            data.price,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        {data.stock_ready >
                                                        0 ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1"
                                                            >
                                                                READY
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        data.stock_ready
                                                                    }
                                                                </span>
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Harga sudah termasuk biaya press. Ready stock
                                berasal dari order batal.
                            </p>
                        </div>

                        {/* Buy section */}
                        {canBuy && (
                            <div className="flex flex-col gap-3 border-t pt-5">
                                <h2 className="text-lg font-medium">
                                    Pesan Sekarang
                                </h2>

                                {/* Size picker */}
                                <div className="flex flex-col gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        Pilih ukuran
                                    </span>
                                    <div className="flex flex-wrap gap-2">
                                        {sizes.map((size) => {
                                            const isSelected =
                                                selectedSize === size;

                                            return (
                                                <button
                                                    key={size}
                                                    type="button"
                                                    data-test={`size-select-${size}`}
                                                    onClick={() =>
                                                        setSelectedSize(size)
                                                    }
                                                    className={`min-w-11 rounded-lg border px-4 py-2 text-sm font-medium transition-colors ${
                                                        isSelected
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'hover:bg-muted'
                                                    }`}
                                                >
                                                    {size}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Qty + price + add */}
                                <div className="flex flex-col gap-3">
                                    {selectedPrice !== null && (
                                        <p className="text-sm">
                                            Harga:{' '}
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(selectedPrice)}
                                            </span>{' '}
                                            / pcs
                                        </p>
                                    )}

                                    <div className="flex flex-wrap items-center gap-3">
                                        {/* Qty controls */}
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-9"
                                                disabled={qty <= 1}
                                                onClick={() =>
                                                    setQty((q) => q - 1)
                                                }
                                                aria-label="Kurangi jumlah"
                                            >
                                                <Minus className="size-4" />
                                            </Button>
                                            <Input
                                                data-test="qty-input"
                                                type="number"
                                                min={1}
                                                value={qty}
                                                onChange={(e) =>
                                                    setQty(
                                                        Math.max(
                                                            1,
                                                            Number(
                                                                e.target.value,
                                                            ) || 1,
                                                        ),
                                                    )
                                                }
                                                className="h-9 w-16 text-center"
                                                aria-label="Jumlah"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-9"
                                                onClick={() =>
                                                    setQty((q) => q + 1)
                                                }
                                                aria-label="Tambah jumlah"
                                            >
                                                <Plus className="size-4" />
                                            </Button>
                                        </div>

                                        <Button
                                            type="button"
                                            data-test="add-to-cart-button"
                                            className="gap-1.5"
                                            disabled={!selectedSize || adding}
                                            onClick={handleAddToCart}
                                        >
                                            <ShoppingCart className="size-4" />
                                            Tambah ke Keranjang
                                        </Button>
                                    </div>

                                    {!selectedSize && (
                                        <p className="text-xs text-muted-foreground">
                                            Pilih ukuran untuk menambahkan ke
                                            keranjang.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

CampaignsShow.layout = {
    breadcrumbs: [{ title: 'Kampanye', href: index.url() }],
};
