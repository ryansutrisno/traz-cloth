import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, Flame, Search } from 'lucide-react';
import { useRef, useState } from 'react';
import CampaignMockup from '@/components/campaign-mockup';
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
    const gridRef = useRef<HTMLDivElement>(null);

    const handleSearch = (value: string) => {
        setQ(value);
        router.get(
            index.url(),
            { q: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const scrollToGrid = () => {
        gridRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    };

    return (
        <>
            <Head title="Jelajahi Kampanye" />
            <div className="flex flex-col">
                {/* Hero — tengah, ala landing campaign */}
                <section
                    data-test="hero-section"
                    className="mx-auto flex max-w-3xl flex-col items-center gap-4 py-10 text-center md:py-16"
                >
                    <h1 className="text-3xl font-bold tracking-tight text-foreground md:text-5xl">
                        Jelajahi Desain Terbaru.
                    </h1>
                    <p className="text-base text-muted-foreground md:text-lg">
                        Dukung desain kaos edisi terbatas dari kreator lokal.
                        Pre-order hari ini dan amankan slot kamu sebelum kuota
                        habis.
                    </p>
                    <div className="flex flex-wrap justify-center gap-3 pt-2">
                        <Button
                            type="button"
                            onClick={scrollToGrid}
                            className="px-6"
                        >
                            Lihat Koleksi
                            <ArrowDown className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled
                            className="gap-1.5 px-6"
                        >
                            Custom Design
                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                Segera
                            </span>
                        </Button>
                    </div>
                </section>

                {/* Bar pencarian */}
                <div className="mb-8 flex flex-col items-center justify-between gap-4 border-b pb-4 md:flex-row">
                    <div className="relative w-full md:max-w-sm">
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
                    <span className="text-sm text-muted-foreground">
                        {pagination.total} desain tersedia
                    </span>
                </div>

                {/* Grid kampanye */}
                <div ref={gridRef} className="flex scroll-mt-24 flex-col gap-3">
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
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {campaigns.data.map((campaign) => (
                                <Link
                                    key={campaign.id}
                                    href={show.url({ slug: campaign.slug })}
                                    data-test="campaign-card"
                                    className="group block focus-visible:outline-none"
                                >
                                    <article className="flex h-full flex-col overflow-hidden rounded-xl border bg-card shadow-sm transition-shadow group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-ring">
                                        {campaign.image_url ? (
                                            <CampaignMockup
                                                src={campaign.image_url}
                                                alt={campaign.title}
                                                badge="Pre-Order"
                                                soldOut={campaign.is_sold_out}
                                            />
                                        ) : (
                                            <div className="flex aspect-[4/5] items-center justify-center bg-gradient-to-br from-muted to-muted-foreground/10">
                                                <span className="text-3xl font-semibold text-muted-foreground/40">
                                                    {campaign.title.charAt(0)}
                                                </span>
                                            </div>
                                        )}

                                        {/* Body */}
                                        <div className="flex flex-1 flex-col p-4 sm:p-5">
                                            <h3 className="line-clamp-2 text-base font-semibold tracking-tight">
                                                {campaign.title}
                                            </h3>

                                            <div className="mt-auto pt-4">
                                                <div className="mb-2 flex justify-between text-xs">
                                                    <span className="font-semibold text-primary">
                                                        {
                                                            campaign.progress_percent
                                                        }
                                                        % terisi
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {campaign.sizes_count}{' '}
                                                        ukuran
                                                    </span>
                                                </div>
                                                <div className="mb-4 h-1 w-full overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={`h-full rounded-full ${
                                                            campaign.is_sold_out
                                                                ? 'bg-destructive'
                                                                : 'bg-primary'
                                                        }`}
                                                        style={{
                                                            width: `${Math.min(100, Math.max(0, campaign.progress_percent))}%`,
                                                        }}
                                                    />
                                                </div>
                                                <div className="flex items-center justify-between border-t pt-3">
                                                    <div className="flex flex-col">
                                                        <span className="text-lg font-semibold tabular-nums">
                                                            {campaign.harga_terendah !==
                                                            null
                                                                ? `Mulai ${formatPrice(campaign.harga_terendah)}`
                                                                : 'Harga belum ditentukan'}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            Pre-order
                                                        </span>
                                                    </div>
                                                    {campaign.is_sold_out ? (
                                                        <span className="rounded bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive">
                                                            Sold Out
                                                        </span>
                                                    ) : (
                                                        <span className="flex items-center gap-1 rounded bg-muted px-2 py-1 text-xs text-muted-foreground">
                                                            <Flame className="size-3.5 text-primary" />
                                                            Limited
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1 py-10">
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
