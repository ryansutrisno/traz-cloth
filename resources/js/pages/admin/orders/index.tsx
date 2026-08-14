import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
import {
    index as adminOrdersIndex,
    show as adminOrdersShow,
} from '@/routes/admin/orders';

type OrderRow = {
    id: number;
    order_number: string;
    status: string;
    payment_mode: 'dp' | 'lunas';
    total_amount: string;
    created_at: string;
    items_count: number;
    user: { name: string; email: string; phone: string };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type StatusOption = { value: string; label: string };

type Props = {
    orders: {
        data: OrderRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters: { q: string; status: string };
    statuses: StatusOption[];
};

const STATUS_LABELS: Record<string, string> = {
    menunggu_pembayaran: 'Menunggu Pembayaran',
    dp_dibayar: 'DP Dibayar',
    produksi: 'Produksi',
    siap_kirim: 'Siap Kirim',
    lunas: 'Lunas',
    dikirim: 'Dikirim',
    delivered: 'Delivered',
    dibatalkan_buyer: 'Dibatalkan Buyer',
    dibatalkan_sistem: 'Dibatalkan Sistem',
};

const STATUS_BADGE_CLASS: Record<string, string> = {
    menunggu_pembayaran: 'bg-amber-500 hover:bg-amber-500',
    dp_dibayar: 'bg-blue-500 hover:bg-blue-500',
    produksi: '',
    siap_kirim: 'bg-violet-500 hover:bg-violet-500',
    lunas: 'bg-green-600 hover:bg-green-600',
    dikirim: 'bg-cyan-500 hover:bg-cyan-500',
    delivered: 'bg-green-700 hover:bg-green-700',
    dibatalkan_buyer: 'bg-red-600 hover:bg-red-600',
    dibatalkan_sistem: 'bg-red-700 hover:bg-red-700',
};

const PAYMENT_MODE_LABELS: Record<string, string> = {
    dp: 'DP',
    lunas: 'Lunas',
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

function formatDate(value: string): string {
    const d = new Date(value);

    if (Number.isNaN(d.getTime())) {
        return value;
    }

    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
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

export default function AdminOrdersIndex({ orders, filters, statuses }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');

    const applyFilters = (nextQ: string, nextStatus: string) => {
        router.get(
            adminOrdersIndex.url(),
            {
                q: nextQ,
                status: nextStatus === 'all' ? '' : nextStatus,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleSearchChange = (value: string) => {
        setQ(value);
    };

    const handleSearchSubmit = () => {
        applyFilters(q, status);
    };

    const handleStatusChange = (value: string) => {
        setStatus(value);
        applyFilters(q, value);
    };

    return (
        <>
            <Head title="Pesanan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Pesanan</h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola semua pesanan dari pembeli
                    </p>
                </div>

                {/* Toolbar */}
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            data-test="orders-search-input"
                            type="search"
                            value={q}
                            onChange={(e) => handleSearchChange(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleSearchSubmit();
                                }
                            }}
                            placeholder="Cari nomor pesanan atau nama..."
                            className="pl-9"
                            aria-label="Cari pesanan"
                        />
                    </div>
                    <Select value={status} onValueChange={handleStatusChange}>
                        <SelectTrigger
                            data-test="orders-status-filter"
                            className="w-[180px]"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        No. Pesanan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pembeli
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Item
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Bayar
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            Tidak ada pesanan ditemukan.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            data-test="order-row"
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-mono font-medium">
                                                    {order.order_number}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {order.user.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {order.user.email}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center tabular-nums">
                                                {order.items_count}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatPrice(
                                                    order.total_amount,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant="secondary">
                                                    {PAYMENT_MODE_LABELS[
                                                        order.payment_mode
                                                    ] ?? order.payment_mode}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant={
                                                        order.status.startsWith(
                                                            'dibatalkan',
                                                        )
                                                            ? 'destructive'
                                                            : 'default'
                                                    }
                                                    className={
                                                        STATUS_BADGE_CLASS[
                                                            order.status
                                                        ] ?? ''
                                                    }
                                                >
                                                    {STATUS_LABELS[
                                                        order.status
                                                    ] ?? order.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDate(order.created_at)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button asChild size="sm">
                                                    <Link
                                                        href={adminOrdersShow.url(
                                                            { order: order.id },
                                                        )}
                                                    >
                                                        Detail
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {orders.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {orders.links.map((link, i) => (
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

AdminOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Pesanan', href: adminOrdersIndex.url() }],
};
