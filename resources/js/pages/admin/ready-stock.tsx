import { Form, Head, Link, router } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    destroy as readyStockDestroy,
    update as readyStockUpdate,
} from '@/routes/admin/ready-stock';
import { show as campaignShow } from '@/routes/campaigns';

type ReadyStockRow = {
    id: number;
    campaign_id: number;
    campaign_slug: string;
    campaign_title: string;
    size: string;
    stock: number;
    updated_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    ready_stock: {
        data: ReadyStockRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    total_stock: number;
};

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

export default function AdminReadyStock({ ready_stock, total_stock }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const handleReset = (row: ReadyStockRow) => {
        if (!window.confirm(`Reset stok ${row.campaign_title} ukuran ${row.size} menjadi 0?`)) {
            return;
        }

        router.delete(readyStockDestroy.url({ campaignSize: row.id }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Ready Stock" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Ready Stock</h1>
                    <p className="text-sm text-muted-foreground">
                        Stok siap dari order batal yang bisa langsung dikirim
                    </p>
                </div>

                {/* Summary */}
                <Card className="gap-2 p-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                            Total Stok Siap
                        </span>
                        <PackageOpen className="size-5 text-muted-foreground" />
                    </div>
                    <p className="text-2xl font-semibold tabular-nums">
                        {total_stock} pcs
                    </p>
                </Card>

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
                                        Ukuran
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Stok
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Update Terakhir
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {ready_stock.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            Belum ada stok ready.
                                        </td>
                                    </tr>
                                ) : (
                                    ready_stock.data.map((row) => (
                                        <tr
                                            key={`${row.campaign_id}-${row.size}`}
                                            data-test="ready-stock-row"
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={campaignShow.url({
                                                        slug: row.campaign_slug,
                                                    })}
                                                    className="font-medium hover:text-primary"
                                                >
                                                    {row.campaign_title}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant="secondary">
                                                    {row.size}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-center font-medium tabular-nums">
                                                {row.stock}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDate(row.updated_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {editingId === row.id ? (
                                                    <Form
                                                        {...readyStockUpdate.form({
                                                            campaignSize: row.id,
                                                        })}
                                                        onSuccess={() =>
                                                            setEditingId(null)
                                                        }
                                                        className="flex items-start justify-end gap-2"
                                                    >
                                                        {({
                                                            processing,
                                                            errors,
                                                        }) => (
                                                            <>
                                                                <div className="grid gap-1">
                                                                    <Input
                                                                        name="stock"
                                                                        type="number"
                                                                        min={0}
                                                                        defaultValue={
                                                                            row.stock
                                                                        }
                                                                        className="h-8 w-24"
                                                                        aria-label={`Stok ${row.campaign_title} ukuran ${row.size}`}
                                                                    />
                                                                    {errors.stock && (
                                                                        <InputError
                                                                            message={
                                                                                errors.stock
                                                                            }
                                                                        />
                                                                    )}
                                                                </div>
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    Simpan
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    onClick={() =>
                                                                        setEditingId(
                                                                            null,
                                                                        )
                                                                    }
                                                                >
                                                                    Batal
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                ) : (
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setEditingId(
                                                                    row.id,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                handleReset(row)
                                                            }
                                                        >
                                                            Reset
                                                        </Button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {ready_stock.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {ready_stock.links.map((link, i) => (
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

AdminReadyStock.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: adminDashboard.url() },
        { title: 'Ready Stock', href: adminDashboard.url() },
    ],
};
