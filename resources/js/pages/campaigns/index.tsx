import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/campaigns';

type CampaignSummary = {
    id: number;
    slug: string;
    title: string;
    image_url: string | null;
    harga_terendah: number | null;
    available: number;
    is_sold_out: boolean;
    progress_percent: number;
    sizes_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    campaigns: { data: CampaignSummary[] };
    filters: { q: string };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return rupiah.format(value);
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

export default function CampaignsIndex({
    campaigns,
    filters,
    pagination,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const handleSearch = (value: string) => {
        setQ(value);
        router.get(
            index.url(),
            { q: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Koleksi Kampanye" />
            <div className="flex flex-col gap-4 md:gap-6">
                {/* Hero */}
                <div className="flex flex-col gap-1 pt-2">
                    <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                        Koleksi Terbatas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Desain kaos pre-order edisi terbatas. Amankan slot kamu
                        sebelum habis.
                    </p>
                </div>

                {/* Search */}
                <div className="relative w-full">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        data-test="search-campaigns-input"
                        type="search"
                        value={q}
                        onChange={(e) => handleSearch(e.target.value)}
                        placeholder="Cari desain..."
                        className="h-10 pl-9"
                        aria-label="Cari desain"
                    />
                </div>

                {/* Grid */}
                {campaigns.data.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-base font-medium">
                            Belum ada kampanye aktif
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Coba ubah kata kunci pencarian atau kembali lagi
                            nanti.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                        {campaigns.data.map((campaign) => (
                            <Link
                                key={campaign.id}
                                href={show.url({ slug: campaign.slug })}
                                data-test="campaign-card"
                                className="group block focus-visible:outline-none"
                            >
                                <div className="overflow-hidden rounded-lg border bg-card transition-shadow group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-ring">
                                    {/* Image — aspect-square for Shopify-like grid */}
                                    <div className="relative aspect-square overflow-hidden bg-muted">
                                        {campaign.image_url ? (
                                            <img
                                                src={campaign.image_url}
                                                alt={campaign.title}
                                                loading="lazy"
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center bg-gradient-to-br from-muted to-muted-foreground/10">
                                                <span className="text-2xl font-semibold text-muted-foreground/40">
                                                    {campaign.title.charAt(0)}
                                                </span>
                                            </div>
                                        )}
                                        {campaign.is_sold_out && (
                                            <Badge
                                                variant="destructive"
                                                className="absolute top-1.5 right-1.5 text-[10px]"
                                            >
                                                Sold Out
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Body — compact */}
                                    <div className="flex flex-col gap-1 p-2.5 sm:p-3">
                                        <h3 className="line-clamp-2 text-sm leading-snug font-medium group-hover:text-primary">
                                            {campaign.title}
                                        </h3>
                                        <p className="text-xs text-muted-foreground sm:text-sm">
                                            {campaign.harga_terendah !== null
                                                ? `Mulai ${formatPrice(campaign.harga_terendah)}`
                                                : 'Harga belum ditentukan'}
                                        </p>
                                        {!campaign.is_sold_out && (
                                            <p className="text-[11px] text-muted-foreground">
                                                Sisa {campaign.available} slot
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1 pt-2">
                        {pagination.links.map((link, i) => (
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
