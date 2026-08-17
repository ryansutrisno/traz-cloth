import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronDown,
    ShieldCheck,
    Sparkles,
    Tag,
    Truck,
    Wallet,
} from 'lucide-react';
import CampaignMockup from '@/components/campaign-mockup';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { index as campaignsIndex, show } from '@/routes/campaigns';

type FeaturedCampaign = {
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

type Props = {
    featured_campaigns: FeaturedCampaign[];
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

const SITE_DESCRIPTION =
    'Traz Cloth — kaos pre-order edisi terbatas. Desain custom hasil kolaborasi, diproduksi terbatas. Amankan slot dengan DP, kami cetak & kirim setelah produksi.';

const FAQ_ITEMS: Array<{ q: string; a: string }> = [
    {
        q: 'Apa itu pre-order limited drop?',
        a: 'Desain dibuat terbatas sesuai kuota. Begitu kuota terisi, drop berakhir dan tidak ada cetak ulang.',
    },
    {
        q: 'Bagaimana cara membayar?',
        a: 'Kamu bisa bayar DP 50% saat checkout dan melunasi sisanya setelah barang siap, atau langsung lunas. DP hangus total jika order dibatalkan.',
    },
    {
        q: 'Kapan pesanan dicetak & dikirim?',
        a: 'Setelah pembayaran dikonfirmasi, pesanan masuk produksi lalu dikirim via kurir dengan nomor resi.',
    },
    {
        q: 'Apa itu ready stock?',
        a: 'Barang siap kirim dari order yang dibatalkan, tanpa perlu menunggu produksi ulang.',
    },
    {
        q: 'Bagaimana cara melacak pesanan?',
        a: 'Nomor resi muncul di halaman Pesanan Saya setelah kurir di-book oleh admin.',
    },
];

export default function Home({ featured_campaigns }: Props) {
    const firstImage =
        featured_campaigns.find((c) => c.image_url)?.image_url ?? null;

    return (
        <>
            <Head title="Traz Cloth — Kaos Pre-Order Limited Drop">
                <meta name="description" content={SITE_DESCRIPTION} />
                <meta
                    property="og:title"
                    content="Traz Cloth — Kaos Pre-Order Limited Drop"
                />
                <meta property="og:description" content={SITE_DESCRIPTION} />
                <meta property="og:type" content="website" />
                {firstImage && (
                    <meta property="og:image" content={firstImage} />
                )}
                <meta name="twitter:card" content="summary_large_image" />
            </Head>

            <div className="flex flex-col gap-6 md:gap-10">
                {/* Hero */}
                <section
                    data-test="home-hero"
                    className="relative flex flex-col gap-4 overflow-hidden rounded-xl border bg-gradient-to-br from-primary/5 via-background to-background px-5 py-8 md:px-10 md:py-14"
                >
                    <div className="flex flex-col gap-3">
                        <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <Sparkles className="size-3" />
                            Pre-Order Limited Drop
                        </span>
                        <h1 className="text-2xl font-bold tracking-tight md:text-4xl lg:text-5xl">
                            Traz Cloth — Kaos Pre-Order Limited Drop
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground md:text-base">
                            Desain custom hasil kolaborasi, diproduksi terbatas.
                            Pilih kaos favorit, amankan slot dengan DP, kami
                            cetak &amp; kirim setelah produksi selesai.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        <Button asChild>
                            <Link
                                href={campaignsIndex.url()}
                                data-test="home-shop-cta"
                            >
                                Lihat Koleksi
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled
                            className="gap-1.5"
                        >
                            Custom Design
                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                Segera
                            </span>
                        </Button>
                    </div>
                </section>

                {/* Featured / Koleksi Terbaru */}
                <section
                    data-test="home-featured-section"
                    className="flex flex-col gap-3"
                >
                    <div className="flex items-end justify-between gap-2">
                        <div className="flex flex-col gap-0.5">
                            <h2 className="text-lg font-semibold tracking-tight md:text-xl">
                                Koleksi Terbaru
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Desain terbatas yang baru saja di-drop.
                            </p>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={campaignsIndex.url()}>
                                Lihat semua
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>

                    {featured_campaigns.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed py-16 text-center">
                            <p className="text-base font-medium">
                                Belum ada koleksi
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Tunggu drop berikutnya ya.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                            {featured_campaigns.map((campaign) => (
                                <Link
                                    key={campaign.id}
                                    href={show.url({ slug: campaign.slug })}
                                    data-test="home-featured-card"
                                    className="group block focus-visible:outline-none"
                                >
                                    <div className="overflow-hidden rounded-lg border bg-card transition-shadow group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-ring">
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

                                        {/* Body — compact */}
                                        <div className="flex flex-col gap-1 p-2.5 sm:p-3">
                                            <h3 className="line-clamp-1 text-sm leading-snug font-medium group-hover:text-primary">
                                                {campaign.title}
                                            </h3>
                                            <p className="text-xs text-muted-foreground sm:text-sm">
                                                {campaign.harga_terendah !==
                                                null
                                                    ? `Mulai ${formatPrice(campaign.harga_terendah)}`
                                                    : 'Harga belum ditentukan'}
                                            </p>
                                            {!campaign.is_sold_out && (
                                                <p className="text-[11px] text-muted-foreground">
                                                    Sisa {campaign.available}{' '}
                                                    slot
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>

                {/* Section kartu info — Cara Belanja */}
                <section
                    data-test="home-info-section"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-col gap-0.5">
                        <h2 className="text-lg font-semibold tracking-tight md:text-xl">
                            Cara Belanja di Traz Cloth
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Empat hal yang perlu kamu tahu sebelum pesan.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                        <div className="flex flex-col gap-2 rounded-lg border bg-card p-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <Tag className="size-4 text-muted-foreground" />
                            </span>
                            <h3 className="text-sm font-semibold">
                                Pre-Order Limited Drop
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Tiap desain dibuat terbatas sesuai kuota. Pesan
                                sebelum slot habis.
                            </p>
                        </div>

                        <div className="flex flex-col gap-2 rounded-lg border bg-card p-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <Wallet className="size-4 text-muted-foreground" />
                            </span>
                            <h3 className="text-sm font-semibold">
                                Bayar DP 50% atau Lunas
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                DP minimal 50% saat checkout, lunasi sisanya
                                setelah barang siap.
                            </p>
                        </div>

                        <div className="flex flex-col gap-2 rounded-lg border bg-card p-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <ShieldCheck className="size-4 text-muted-foreground" />
                            </span>
                            <h3 className="text-sm font-semibold">
                                Pembayaran Aman
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Transaksi diproses lewat Midtrans yang
                                terpercaya.
                            </p>
                        </div>

                        <div className="flex flex-col gap-2 rounded-lg border bg-card p-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <Truck className="size-4 text-muted-foreground" />
                            </span>
                            <h3 className="text-sm font-semibold">
                                Dicetak &amp; Dikirim
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Desain diproses cetak setelah pembayaran, lalu
                                dikirim dengan resi.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Section FAQ */}
                <section
                    data-test="home-faq-section"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-col gap-0.5">
                        <h2 className="text-lg font-semibold tracking-tight md:text-xl">
                            Pertanyaan Umum
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Hal-hal yang sering ditanyakan tentang pre-order.
                        </p>
                    </div>

                    <div className="flex flex-col divide-y rounded-lg border">
                        {FAQ_ITEMS.map((item) => (
                            <Collapsible
                                key={item.q}
                                data-test="faq-item"
                                className="flex flex-col"
                            >
                                <CollapsibleTrigger className="group flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-sm font-medium transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                    {item.q}
                                    <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="flex flex-col">
                                    <p className="px-4 pb-4 text-sm text-muted-foreground">
                                        {item.a}
                                    </p>
                                </CollapsibleContent>
                            </Collapsible>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
