import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Lock,
    Minus,
    PenTool,
    Plus,
    Ruler,
    ShoppingCart,
    TrendingUp,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
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

    const firstPrice = sizes[0]
        ? price_by_size[sizes[0]].price
        : campaign.base_price;
    const displayPrice =
        selectedSize !== '' && price_by_size[selectedSize]
            ? price_by_size[selectedSize].price
            : firstPrice;

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
            <div className="flex flex-col gap-8">
                {/* Breadcrumb */}
                <nav className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link
                        href={index.url()}
                        className="transition-colors hover:text-primary"
                    >
                        Katalog
                    </Link>
                    <ChevronRight className="size-4" />
                    <span className="truncate font-medium text-foreground">
                        {campaign.title}
                    </span>
                </nav>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-10">
                    {/* Kolom kiri: visual & detail */}
                    <div className="flex flex-col gap-10 lg:col-span-7">
                        {/* Visual utama */}
                        <div className="relative aspect-square overflow-hidden rounded-xl border bg-muted md:aspect-[4/3]">
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
                            <span className="absolute top-4 left-4 rounded-full bg-background/90 px-3 py-1 text-xs font-medium tracking-wider text-foreground uppercase backdrop-blur-sm">
                                Mockup
                            </span>
                        </div>

                        {/* Tentang desain */}
                        <section className="flex flex-col gap-4">
                            <h2 className="flex items-center gap-3 text-xl font-semibold tracking-tight md:text-2xl">
                                <PenTool className="size-5 text-primary" />
                                Tentang Desain
                            </h2>
                            {campaign.description ? (
                                <p className="text-sm whitespace-pre-line text-muted-foreground md:text-base">
                                    {campaign.description}
                                </p>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada deskripsi untuk desain ini.
                                </p>
                            )}
                        </section>

                        {/* Ukuran & harga */}
                        <section className="flex flex-col gap-4 border-t pt-8">
                            <h2 className="flex items-center gap-3 text-xl font-semibold tracking-tight md:text-2xl">
                                <Ruler className="size-5 text-primary" />
                                Ukuran &amp; Harga
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
                                                                <Check className="size-3 text-green-600" />
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
                        </section>
                    </div>

                    {/* Kolom kanan: kartu aksi sticky */}
                    <div className="relative lg:col-span-5">
                        <div className="flex flex-col gap-6 lg:sticky lg:top-24">
                            <Card className="gap-0 p-6 shadow-sm md:p-8">
                                <div className="mb-4 flex flex-col gap-2">
                                    <span
                                        className={`inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold tracking-wider uppercase ${
                                            is_sold_out
                                                ? 'bg-destructive/10 text-destructive'
                                                : 'bg-primary/10 text-primary'
                                        }`}
                                    >
                                        {is_sold_out
                                            ? 'Sold Out'
                                            : 'Kampanye Aktif'}
                                    </span>
                                    <h1
                                        data-test="campaign-title"
                                        className="text-2xl leading-tight font-bold tracking-tight md:text-3xl"
                                    >
                                        {campaign.title}
                                    </h1>
                                </div>

                                {/* Harga dinamis */}
                                <div className="mb-8 text-3xl font-bold tabular-nums">
                                    {formatPrice(displayPrice)}
                                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                                        / pcs
                                    </span>
                                </div>

                                {/* Progres kuota */}
                                <div className="mb-8">
                                    <div className="mb-2 flex items-end justify-between">
                                        <div>
                                            <span className="block text-xl font-semibold tabular-nums">
                                                {campaign.sold_count}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                Slot terisi
                                            </span>
                                        </div>
                                        <span className="text-xs font-medium text-primary">
                                            Kuota: {campaign.quota}
                                        </span>
                                    </div>
                                    <div className="relative mb-3 h-3 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full rounded-full ${
                                                is_sold_out
                                                    ? 'bg-destructive'
                                                    : 'bg-primary'
                                            }`}
                                            style={{
                                                width: `${Math.min(100, Math.max(0, progress_percent))}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="flex items-center gap-1 font-medium text-primary">
                                            <TrendingUp className="size-3.5" />
                                            {progress_percent}% terisi
                                        </span>
                                        <span className="font-medium text-muted-foreground">
                                            {is_sold_out
                                                ? 'Slot habis'
                                                : `Sisa ${available} slot`}
                                        </span>
                                    </div>
                                </div>

                                {is_sold_out && (
                                    <Alert
                                        variant="destructive"
                                        className="mb-6"
                                    >
                                        <AlertTitle>Slot habis</AlertTitle>
                                        <AlertDescription>
                                            Kampanye ini sudah sold out. Slot
                                            pre-order tidak tersedia lagi.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Aksi beli */}
                                {canBuy && (
                                    <div className="flex flex-col gap-4">
                                        <div>
                                            <span className="mb-2 block text-sm font-medium">
                                                Pilih ukuran
                                            </span>
                                            <div className="grid grid-cols-5 gap-2">
                                                {sizes.map((size) => {
                                                    const isSelected =
                                                        selectedSize === size;

                                                    return (
                                                        <button
                                                            key={size}
                                                            type="button"
                                                            data-test={`size-select-${size}`}
                                                            onClick={() =>
                                                                setSelectedSize(
                                                                    size,
                                                                )
                                                            }
                                                            className={`rounded-lg py-2 text-sm transition-colors ${
                                                                isSelected
                                                                    ? 'border-2 border-primary bg-primary/5 font-bold text-primary'
                                                                    : 'border font-medium hover:border-primary hover:bg-primary/5'
                                                            }`}
                                                        >
                                                            {size}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        <div>
                                            <span className="mb-2 block text-sm font-medium">
                                                Jumlah
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
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
                                                                    e.target
                                                                        .value,
                                                                ) || 1,
                                                            ),
                                                        )
                                                    }
                                                    className="h-10 w-16 text-center"
                                                    aria-label="Jumlah"
                                                />
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setQty((q) => q + 1)
                                                    }
                                                    aria-label="Tambah jumlah"
                                                >
                                                    <Plus className="size-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            data-test="add-to-cart-button"
                                            className="h-12 w-full gap-2 text-base"
                                            disabled={!selectedSize || adding}
                                            onClick={handleAddToCart}
                                        >
                                            {adding ? (
                                                <Spinner />
                                            ) : (
                                                <ShoppingCart className="size-5" />
                                            )}
                                            Tambah ke Keranjang
                                        </Button>

                                        {!selectedSize && (
                                            <p className="text-center text-xs text-muted-foreground">
                                                Pilih ukuran untuk menambahkan
                                                ke keranjang.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Trust badges */}
                                <div className="mt-6 flex justify-center gap-6 opacity-80">
                                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Lock className="size-4" />
                                        Pembayaran aman
                                    </div>
                                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Truck className="size-4" />
                                        Kirim seluruh Indonesia
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

CampaignsShow.layout = {
    breadcrumbs: [{ title: 'Kampanye', href: index.url() }],
};
