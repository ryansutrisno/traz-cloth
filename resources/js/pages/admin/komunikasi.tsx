import { Head, Link, router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard as adminDashboard } from '@/routes/admin';
import { send as komunikasiSend } from '@/routes/admin/komunikasi';

type Buyer = {
    id: number;
    name: string;
    email: string;
    phone: string;
};

type WaMessage = {
    id: number;
    phone: string;
    template_key: string;
    status: string;
    sent_at: string | null;
    payload: { message?: string } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    buyers: {
        data: Buyer[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    wa_messages: WaMessage[];
    filters: { q: string };
};

const WA_STATUS_LABELS: Record<string, string> = {
    menunggu: 'Menunggu',
    terkirim: 'Terkirim',
    gagal: 'Gagal',
    terjadwal: 'Terjadwal',
};

const WA_STATUS_BADGE: Record<
    string,
    { variant: 'default' | 'destructive' | 'secondary'; className: string }
> = {
    menunggu: {
        variant: 'default',
        className: 'bg-amber-500 hover:bg-amber-500',
    },
    terkirim: {
        variant: 'default',
        className: 'bg-green-600 hover:bg-green-600',
    },
    gagal: { variant: 'destructive', className: '' },
    terjadwal: { variant: 'secondary', className: '' },
};

const textAreaClass =
    'flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

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

export default function AdminKomunikasi({
    buyers,
    wa_messages,
}: Props) {
    const [selectedBuyerId, setSelectedBuyerId] = useState('');
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);

    const handleSend = () => {
        if (!selectedBuyerId) {
            toast.error('Pilih pembeli terlebih dahulu');

            return;
        }

        if (!message.trim()) {
            toast.error('Pesan tidak boleh kosong');

            return;
        }

        setSending(true);
        router.post(
            komunikasiSend.url(),
            { user_id: Number(selectedBuyerId), message },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSending(false);
                    setMessage('');
                    toast.success('Pesan masuk antrean');
                },
                onError: (err) => {
                    setSending(false);
                    const msg =
                        (err.message as string | undefined) ??
                        'Gagal mengirim pesan';
                    toast.error(msg);
                },
            },
        );
    };

    return (
        <>
            <Head title="Komunikasi" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Komunikasi</h1>
                    <p className="text-sm text-muted-foreground">
                        Kirim pesan WhatsApp ke pembeli
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                    {/* Send manual */}
                    <Card className="gap-4 p-4">
                        <h2 className="font-medium">Kirim Pesan Manual</h2>
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="buyer">Pilih Pembeli</Label>
                                <Select
                                    value={selectedBuyerId}
                                    onValueChange={setSelectedBuyerId}
                                >
                                    <SelectTrigger
                                        id="buyer"
                                        data-test="komunikasi-buyer-select"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih pembeli" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {buyers.data.map((buyer) => (
                                            <SelectItem
                                                key={buyer.id}
                                                value={String(buyer.id)}
                                            >
                                                {buyer.name} ({buyer.phone})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="message">Pesan</Label>
                                <textarea
                                    id="message"
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                    maxLength={1000}
                                    className={textAreaClass}
                                    placeholder="Tulis pesan WhatsApp..."
                                />
                                <p className="text-right text-xs text-muted-foreground">
                                    {message.length}/1000
                                </p>
                            </div>

                            <Button
                                type="button"
                                className="w-full gap-1.5"
                                data-test="send-wa-button"
                                disabled={sending}
                                onClick={handleSend}
                            >
                                {sending ? (
                                    <Spinner />
                                ) : (
                                    <Send className="size-4" />
                                )}
                                Kirim
                            </Button>
                        </div>
                    </Card>

                    {/* Outbox */}
                    <Card className="gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">
                                Riwayat Pesan (Outbox)
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {wa_messages.length} pesan
                            </span>
                        </div>
                        {wa_messages.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Belum ada pesan terkirim.
                            </p>
                        ) : (
                            <div className="flex flex-col divide-y">
                                {wa_messages.map((msg) => {
                                    const badge = WA_STATUS_BADGE[
                                        msg.status
                                    ] ?? {
                                        variant: 'secondary' as const,
                                        className: '',
                                    };

                                    return (
                                        <div
                                            key={msg.id}
                                            data-test="wa-outbox-row"
                                            className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-mono text-sm font-medium">
                                                    {msg.phone}
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">
                                                        {msg.template_key}
                                                    </Badge>
                                                    <Badge
                                                        variant={badge.variant}
                                                        className={
                                                            badge.className
                                                        }
                                                    >
                                                        {WA_STATUS_LABELS[
                                                            msg.status
                                                        ] ?? msg.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                            {msg.payload?.message && (
                                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                                    {msg.payload.message}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(msg.sent_at)}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Pagination buyers */}
                        {buyers.last_page > 1 && (
                            <div className="flex items-center justify-center gap-1 border-t pt-3">
                                {buyers.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
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
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminKomunikasi.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: adminDashboard.url() },
        { title: 'Komunikasi', href: adminDashboard.url() },
    ],
};
