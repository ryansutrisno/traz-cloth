import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, MapPin, Package, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as addressesIndex } from '@/routes/addresses';
import { update, verify } from '@/routes/account/phone';
import { index as ordersIndex } from '@/routes/orders';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

type Address = {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    full_address: string;
    city: string;
    province: string;
    postal_code: string;
};

type Props = {
    user: {
        name: string;
        email: string;
        phone: string | null;
        phone_verified: boolean;
    };
    statistics: {
        total: number;
        in_progress: number;
        completed: number;
    };
    default_address: Address | null;
    phone_verification: {
        pending: boolean;
        phone: string | null;
        resend_cooldown: number;
    };
};

type PhoneStep = 'phone' | 'code';

export default function AccountIndex({
    user,
    statistics,
    default_address,
    phone_verification,
}: Props) {
    const [phoneDialogOpen, setPhoneDialogOpen] = useState(
        phone_verification.pending,
    );
    const [phoneStep, setPhoneStep] = useState<PhoneStep>(
        phone_verification.pending ? 'code' : 'phone',
    );
    const phoneForm = useForm({ phone: '' });
    const otpForm = useForm({ otp: '' });

    useEffect(() => {
        if (phone_verification.pending) {
            setPhoneStep('code');
            setPhoneDialogOpen(true);
        }
    }, [phone_verification.pending]);

    const handlePhoneSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        phoneForm.post(update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setPhoneStep('code');
                setPhoneDialogOpen(true);
                toast.success('Kode OTP sudah dikirim ke nomor baru.');
            },
            onError: () => toast.error('Nomor WhatsApp belum dapat diverifikasi.'),
        });
    };

    const handleOtpSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        otpForm.post(verify.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setPhoneDialogOpen(false);
                setPhoneStep('phone');
                phoneForm.reset();
                otpForm.reset();
                toast.success('Nomor WhatsApp berhasil diverifikasi.');
            },
            onError: () => toast.error('Kode OTP salah atau sudah kedaluwarsa.'),
        });
    };

    return (
        <>
            <Head title="Akun Saya" />
            <div className="flex flex-col gap-8">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Akun Saya
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Kelola informasi akun dan pantau aktivitas pesanan kamu.
                    </p>
                </header>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="gap-2 p-5">
                        <Package className="size-5 text-primary" />
                        <span className="text-3xl font-bold tabular-nums">
                            {statistics.total}
                        </span>
                        <span className="text-sm text-muted-foreground">Total Pesanan</span>
                    </Card>
                    <Card className="gap-2 p-5">
                        <ShieldCheck className="size-5 text-amber-500" />
                        <span className="text-3xl font-bold tabular-nums">
                            {statistics.in_progress}
                        </span>
                        <span className="text-sm text-muted-foreground">Sedang Berjalan</span>
                    </Card>
                    <Card className="gap-2 p-5">
                        <CheckCircle2 className="size-5 text-green-600" />
                        <span className="text-3xl font-bold tabular-nums">
                            {statistics.completed}
                        </span>
                        <span className="text-sm text-muted-foreground">Pesanan Selesai</span>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-5 p-5 md:p-6">
                        <div>
                            <h2 className="text-lg font-semibold">Informasi Pribadi</h2>
                            <p className="text-sm text-muted-foreground">
                                Data akun yang terhubung dengan pesanan kamu.
                            </p>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <div className="flex flex-col gap-1">
                                <dt className="text-muted-foreground">Nama</dt>
                                <dd className="font-medium">{user.name}</dd>
                            </div>
                            <div className="flex flex-col gap-1">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="font-medium">{user.email}</dd>
                            </div>
                            <div className="flex flex-col gap-1">
                                <dt className="text-muted-foreground">WhatsApp</dt>
                                <dd className="flex flex-wrap items-center gap-2 font-medium">
                                    {user.phone ?? 'Belum ditambahkan'}
                                    {user.phone_verified && (
                                        <span className="text-xs font-normal text-green-600">
                                            Terverifikasi
                                        </span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={() => setPhoneDialogOpen(true)}
                        >
                            Verifikasi Ulang WhatsApp
                        </Button>
                    </Card>

                    <Card className="gap-5 p-5 md:p-6">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">Alamat Utama</h2>
                                <p className="text-sm text-muted-foreground">
                                    Alamat yang digunakan sebagai tujuan pengiriman.
                                </p>
                            </div>
                            <MapPin className="size-5 text-primary" />
                        </div>
                        {default_address ? (
                            <div className="rounded-lg bg-muted/50 p-4 text-sm">
                                <p className="font-medium">
                                    {default_address.label} · {default_address.recipient_name}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {default_address.full_address}, {default_address.city},{' '}
                                    {default_address.province} {default_address.postal_code}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {default_address.phone}
                                </p>
                            </div>
                        ) : (
                            <p className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                                Belum ada alamat utama.
                            </p>
                        )}
                        <Button asChild variant="outline" className="w-fit">
                            <Link href={addressesIndex.url()}>Kelola Alamat</Link>
                        </Button>
                    </Card>
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">Akses Cepat</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Button asChild variant="outline" className="h-auto justify-between py-4">
                            <Link href={ordersIndex.url()}>
                                Pesanan Saya <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="h-auto justify-between py-4">
                            <Link href={addressesIndex.url()}>
                                Alamat <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="h-auto justify-between py-4">
                            <Link href={profileEdit.url()}>
                                Pengaturan Profil <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="h-auto justify-between py-4">
                            <Link href={securityEdit.url()}>
                                Password &amp; Keamanan <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </section>
            </div>

            <Dialog open={phoneDialogOpen} onOpenChange={setPhoneDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {phoneStep === 'phone'
                                ? 'Ganti Nomor WhatsApp'
                                : 'Masukkan Kode OTP'}
                        </DialogTitle>
                        <DialogDescription>
                            {phoneStep === 'phone'
                                ? 'Kami akan mengirim kode verifikasi ke nomor WhatsApp baru kamu.'
                                : `Kode dikirim ke ${phone_verification.phone ?? 'nomor baru'}.`}
                        </DialogDescription>
                    </DialogHeader>

                    {phoneStep === 'phone' ? (
                        <form onSubmit={handlePhoneSubmit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="account-phone">Nomor WhatsApp baru</Label>
                                <Input
                                    id="account-phone"
                                    type="tel"
                                    value={phoneForm.data.phone}
                                    onChange={(event) =>
                                        phoneForm.setData('phone', event.target.value)
                                    }
                                    placeholder="0812xxxxxxx"
                                    required
                                />
                                <InputError message={phoneForm.errors.phone} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={phoneForm.processing}>
                                    {phoneForm.processing ? 'Mengirim...' : 'Kirim Kode OTP'}
                                </Button>
                            </DialogFooter>
                        </form>
                    ) : (
                        <form onSubmit={handleOtpSubmit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="account-otp">Kode OTP</Label>
                                <Input
                                    id="account-otp"
                                    inputMode="numeric"
                                    maxLength={6}
                                    value={otpForm.data.otp}
                                    onChange={(event) =>
                                        otpForm.setData('otp', event.target.value)
                                    }
                                    placeholder="000000"
                                    required
                                />
                                <InputError message={otpForm.errors.otp} />
                                {phone_verification.resend_cooldown > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Kode baru dapat dikirim lagi dalam {phone_verification.resend_cooldown} detik.
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={otpForm.processing}>
                                    {otpForm.processing ? 'Memverifikasi...' : 'Verifikasi Nomor'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
