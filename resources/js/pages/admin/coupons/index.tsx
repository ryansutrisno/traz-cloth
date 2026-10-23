import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
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
import { create, destroy, edit, index } from '@/routes/admin/coupons';

type CouponRow = {
    id: number;
    code: string;
    description: string | null;
    type: 'percentage' | 'fixed';
    type_label: string;
    value: number;
    min_purchase: number | null;
    max_discount: number | null;
    used_count: number;
    usage_limit: number | null;
    per_user_limit: number | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    created_at: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Props = {
    coupons: {
        data: CouponRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters: { q: string; type: string; status: string };
    types: { value: string; label: string }[];
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          });
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

export default function AdminCouponsIndex({ coupons, filters, types }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [type, setType] = useState(filters.type ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');

    const visit = (next: { q?: string; type?: string; status?: string }) => {
        router.get(
            index.url(),
            {
                q: next.q ?? q,
                type: (next.type ?? type) === 'all' ? '' : (next.type ?? type),
                status:
                    (next.status ?? status) === 'all'
                        ? ''
                        : (next.status ?? status),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleDelete = (coupon: CouponRow) => {
        if (!window.confirm('Hapus kupon ini?')) {
            return;
        }

        router.delete(destroy.url({ coupon: coupon.id }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Kelola Kupon" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Kelola Kupon</h1>
                        <p className="text-sm text-muted-foreground">
                            Atur diskon untuk pelanggan.
                        </p>
                    </div>
                    <Button asChild size="sm" data-test="create-coupon-button">
                        <Link href={create.url()} className="gap-1.5">
                            <Plus className="size-4" />
                            Kupon Baru
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => {
                                setQ(event.target.value);
                                visit({ q: event.target.value });
                            }}
                            placeholder="Cari kode atau deskripsi..."
                            className="pl-9"
                            aria-label="Cari kupon"
                        />
                    </div>
                    <Select
                        value={type}
                        onValueChange={(value) => {
                            setType(value);
                            visit({ type: value });
                        }}
                    >
                        <SelectTrigger className="w-[170px]">
                            <SelectValue placeholder="Tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua tipe</SelectItem>
                            {types.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={status}
                        onValueChange={(value) => {
                            setStatus(value);
                            visit({ status: value });
                        }}
                    >
                        <SelectTrigger className="w-[170px]">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="inactive">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Kode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tipe
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Nilai
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Min. Belanja
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Kuota
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Periode
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
                                {coupons.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            Belum ada kupon.
                                        </td>
                                    </tr>
                                ) : (
                                    coupons.data.map((coupon) => (
                                        <tr
                                            key={coupon.id}
                                            data-test="coupon-row"
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="font-mono font-medium">
                                                        {coupon.code}
                                                    </span>
                                                    {coupon.description && (
                                                        <span className="max-w-48 truncate text-xs text-muted-foreground">
                                                            {coupon.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {coupon.type_label}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {coupon.type === 'percentage'
                                                    ? `${coupon.value}%`
                                                    : rupiah.format(
                                                          coupon.value,
                                                      )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {coupon.min_purchase === null
                                                    ? '—'
                                                    : rupiah.format(
                                                          coupon.min_purchase,
                                                      )}
                                            </td>
                                            <td className="px-4 py-3 text-center tabular-nums">
                                                {coupon.used_count}/
                                                {coupon.usage_limit ?? '∞'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatDate(coupon.starts_at)} –{' '}
                                                {formatDate(coupon.expires_at)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant={
                                                        coupon.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className={
                                                        coupon.is_active
                                                            ? 'bg-green-600 hover:bg-green-600'
                                                            : 'opacity-60'
                                                    }
                                                >
                                                    {coupon.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
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
                                                                coupon: coupon.id,
                                                            })}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            handleDelete(coupon)
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

                {coupons.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {coupons.links.map((link, i) => (
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

AdminCouponsIndex.layout = {
    breadcrumbs: [{ title: 'Kupon', href: index.url() }],
};
