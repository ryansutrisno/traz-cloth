import { Head, Link } from '@inertiajs/react';
import { TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as laporanIndex } from '@/routes/admin/laporan';

type ReportRow = {
    id: number;
    slug: string;
    title: string;
    qty_terjual: number;
    revenue_dp: string;
    revenue_pelunasan: string;
    revenue_full: string;
    revenue_total: string;
    biaya_produksi: string;
    margin: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    report: {
        data: ReportRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    summaries: {
        revenue_total: string;
        biaya_total: string;
        margin_total: string;
    };
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatPrice(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    const n = Number(value);

    if (Number.isNaN(n)) {
        return '—';
    }

    return rupiah.format(n);
}

function marginClass(value: string): string {
    const n = Number(value);

    if (Number.isNaN(n)) {
        return '';
    }

    if (n > 0) {
        return 'text-green-600 dark:text-green-400';
    }

    if (n < 0) {
        return 'text-red-600 dark:text-red-400';
    }

    return '';
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

function SummaryCard({
    label,
    value,
    icon: Icon,
    tone,
}: {
    label: string;
    value: string;
    icon: typeof Wallet;
    tone: 'neutral' | 'positive' | 'negative';
}) {
    const toneClass =
        tone === 'positive'
            ? 'text-green-600 dark:text-green-400'
            : tone === 'negative'
              ? 'text-red-600 dark:text-red-400'
              : '';

    return (
        <Card className="gap-2 p-4">
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span className="flex size-8 items-center justify-center rounded-lg bg-muted">
                    <Icon className="size-4 text-muted-foreground" />
                </span>
            </div>
            <p className={`text-2xl font-semibold tabular-nums ${toneClass}`}>
                {value}
            </p>
        </Card>
    );
}

export default function AdminLaporan({ report, summaries }: Props) {
    const marginNum = Number(summaries.margin_total);

    return (
        <>
            <Head title="Laporan Keuangan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Laporan Keuangan</h1>
                    <p className="text-sm text-muted-foreground">
                        Rekap revenue dan margin per kampanye
                    </p>
                </div>

                {/* Summary */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Total Revenue"
                        value={formatPrice(summaries.revenue_total)}
                        icon={Wallet}
                        tone="neutral"
                    />
                    <SummaryCard
                        label="Total Biaya Produksi"
                        value={formatPrice(summaries.biaya_total)}
                        icon={TrendingDown}
                        tone="neutral"
                    />
                    <SummaryCard
                        label="Total Margin"
                        value={formatPrice(summaries.margin_total)}
                        icon={TrendingUp}
                        tone={
                            marginNum > 0
                                ? 'positive'
                                : marginNum < 0
                                  ? 'negative'
                                  : 'neutral'
                        }
                    />
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Kampanye
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Qty Terjual
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Revenue DP
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Revenue Pelunasan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Revenue Lunas
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total Revenue
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Biaya Produksi
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Margin
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            Belum ada data laporan.
                                        </td>
                                    </tr>
                                ) : (
                                    report.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            data-test="report-row"
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {row.title}
                                            </td>
                                            <td className="px-4 py-3 text-center tabular-nums">
                                                {row.qty_terjual}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatPrice(row.revenue_dp)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatPrice(
                                                    row.revenue_pelunasan,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatPrice(row.revenue_full)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatPrice(row.revenue_total)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatPrice(
                                                    row.biaya_produksi,
                                                )}
                                            </td>
                                            <td
                                                data-test="report-margin"
                                                className={`px-4 py-3 text-right font-medium tabular-nums ${marginClass(row.margin)}`}
                                            >
                                                {formatPrice(row.margin)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {report.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {report.links.map((link, i) => (
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

AdminLaporan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: adminDashboard.url() },
        { title: 'Laporan', href: laporanIndex.url() },
    ],
};
