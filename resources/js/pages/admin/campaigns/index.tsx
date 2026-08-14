import { Head, Link, router } from '@inertiajs/react';
import { LogOut, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, destroy, edit, index } from '@/routes/admin/campaigns';
import { logout } from '@/routes/admin';

type AdminCampaign = {
    id: number;
    slug: string;
    title: string;
    image_url: string | null;
    base_price: string;
    quota: number;
    sold_count: number;
    status: string;
    created_at: string;
    sizes_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    campaigns: {
        data: AdminCampaign[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters: { q: string; status: string };
};

const STATUS_OPTIONS = ['draft', 'active', 'sold_out', 'disabled'] as const;

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    sold_out: 'Sold Out',
    disabled: 'Nonaktif',
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'active'
            ? 'default'
            : status === 'sold_out'
              ? 'destructive'
              : 'secondary';
    const className =
        status === 'active'
            ? 'bg-green-600 hover:bg-green-600'
            : status === 'disabled'
              ? 'opacity-60'
              : '';

    return (
        <Badge variant={variant} className={className}>
            {STATUS_LABELS[status] ?? status}
        </Badge>
    );
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

export default function AdminCampaignsIndex({ campaigns, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');

    const handleSearch = (value: string) => {
        setQ(value);
        router.get(
            index.url(),
            { q: value, status: status === 'all' ? '' : status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleStatusChange = (value: string) => {
        setStatus(value);
        router.get(
            index.url(),
            { q, status: value === 'all' ? '' : value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleDelete = (campaign: AdminCampaign) => {
        if (!window.confirm('Hapus kampanye ini?')) {
            return;
        }

        router.delete(destroy.url({ campaign: campaign.id }), {
            preserveScroll: true,
        });
    };

    const handleLogout = () => {
        router.post(logout.url());
    };

    return (
        <>
            <Head title="Kelola Kampanye" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-semibold">
                            Admin Traz Cloth
                        </h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={index.url()}>Kampanye</Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={handleLogout}
                            className="gap-1.5"
                        >
                            <LogOut className="size-4" />
                            Keluar
                        </Button>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                        <div className="relative w-full max-w-xs">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={q}
                                onChange={(e) => handleSearch(e.target.value)}
                                placeholder="Cari judul atau slug..."
                                className="pl-9"
                                aria-label="Cari kampanye"
                            />
                        </div>
                        <Select
                            value={status}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_LABELS[s]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        data-test="create-campaign-button"
                    >
                        <Link href={create.url()} className="gap-1.5">
                            <Plus className="size-4" />
                            Buat Kampanye
                        </Link>
                    </Button>
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
                                    <th className="px-4 py-3 text-right font-medium">
                                        Harga Dasar
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Kuota
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {campaigns.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            Belum ada kampanye.
                                        </td>
                                    </tr>
                                ) : (
                                    campaigns.data.map((campaign) => (
                                        <tr
                                            key={campaign.id}
                                            data-test="campaign-row"
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="size-10 shrink-0 overflow-hidden rounded-md bg-muted">
                                                        {campaign.image_url ? (
                                                            <img
                                                                src={
                                                                    campaign.image_url
                                                                }
                                                                alt=""
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex size-full items-center justify-center text-xs font-semibold text-muted-foreground/50">
                                                                {campaign.title.charAt(
                                                                    0,
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">
                                                            {campaign.title}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            /{campaign.slug}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {rupiah.format(
                                                    Number(
                                                        campaign.base_price,
                                                    ) || 0,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center tabular-nums">
                                                {campaign.sold_count}/
                                                {campaign.quota}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <StatusBadge
                                                    status={campaign.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={edit.url({
                                                                campaign:
                                                                    campaign.id,
                                                            })}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            handleDelete(
                                                                campaign,
                                                            )
                                                        }
                                                    >
                                                        Hapus
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {campaigns.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {campaigns.links.map((link, i) => (
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

AdminCampaignsIndex.layout = {
    breadcrumbs: [{ title: 'Kampanye', href: index.url() }],
};
