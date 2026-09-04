import { Form, Head, router } from '@inertiajs/react';
import { ArrowRight, Lock, Send, ShieldCheck } from 'lucide-react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import type { ComponentRef } from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { check, resend } from '@/routes/verify-otp';

type Props = {
    phone: string;
    resendAfter?: number;
};

const OTP_LENGTH = 6;

export default function VerifyOtp({ phone, resendAfter = 60 }: Props) {
    const [code, setCode] = useState('');
    const [countdown, setCountdown] = useState(resendAfter);
    const [resending, setResending] = useState(false);
    const formRef = useRef<ComponentRef<typeof Form>>(null);

    // Countdown timer for the resend cooldown.
    useEffect(() => {
        if (countdown <= 0) {
            return;
        }

        const timer = setTimeout(() => setCountdown((c) => c - 1), 1000);

        return () => clearTimeout(timer);
    }, [countdown]);

    // Auto-submit once all digits are entered (mobile-friendly).
    useEffect(() => {
        if (code.length === OTP_LENGTH) {
            formRef.current?.submit();
        }
    }, [code]);

    const handleResend = () => {
        if (countdown > 0 || resending) {
            return;
        }

        setResending(true);
        router.post(
            resend.url(),
            { phone },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCode('');
                    setCountdown(resendAfter);
                },
                onFinish: () => setResending(false),
            },
        );
    };

    return (
        <>
            <Head title="Verifikasi OTP" />
            <Form<Record<string, any>>
                ref={formRef}
                {...check.form()}
                disableWhileProcessing
                className="flex w-full flex-col"
            >
                {({ processing, errors }) => (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        {/* Strip gradasi atas ala kartu Stitch */}
                        <div className="h-1 bg-gradient-to-r from-primary to-primary/30" />

                        <div className="flex flex-col gap-6 p-6 sm:p-8">
                            <input type="hidden" name="phone" value={phone} />

                            {/* Ikon verifikasi */}
                            <div className="flex justify-center">
                                <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <ShieldCheck className="size-7" />
                                </span>
                            </div>

                            <p className="text-center text-sm text-muted-foreground">
                                Kode dikirim ke WhatsApp{' '}
                                <span className="font-medium text-foreground">
                                    {phone}
                                </span>
                            </p>

                            {/* Input OTP */}
                            <div className="flex flex-col items-center gap-3">
                                <Label htmlFor="otp" className="sr-only">
                                    Kode verifikasi
                                </Label>
                                <InputOTP
                                    id="otp"
                                    name="otp"
                                    maxLength={OTP_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    autoFocus
                                    aria-describedby="otp-error"
                                >
                                    <InputOTPGroup>
                                        {Array.from(
                                            { length: OTP_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                    className="h-11 w-11 text-base sm:h-12 sm:w-12 sm:text-lg"
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError
                                    id="otp-error"
                                    message={errors.otp}
                                />
                            </div>

                            {/* Aksi utama */}
                            <Button
                                type="submit"
                                className="h-12 w-full text-base"
                                disabled={
                                    processing || code.length < OTP_LENGTH
                                }
                                data-test="verify-otp-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <>
                                        Verifikasi
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </Button>

                            {/* Kirim ulang & ganti nomor */}
                            <div className="flex flex-col gap-4 border-t pt-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Belum menerima kode?
                                    </span>
                                    <button
                                        type="button"
                                        onClick={handleResend}
                                        disabled={resending || countdown > 0}
                                        data-test="resend-otp-button"
                                        className="inline-flex items-center gap-1.5 font-medium text-primary transition-colors hover:text-primary/80 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {resending ? (
                                            <Spinner />
                                        ) : (
                                            <Send className="size-4" />
                                        )}
                                        {resending
                                            ? 'Mengirim...'
                                            : countdown > 0
                                              ? `Kirim ulang ${countdown}s`
                                              : 'Kirim ulang kode'}
                                    </button>
                                </div>
                                <div className="text-center">
                                    <TextLink href={register()}>
                                        Ganti nomor
                                    </TextLink>
                                </div>
                            </div>
                        </div>

                        {/* Catatan keamanan */}
                        <div className="flex items-center justify-center gap-1.5 border-t bg-muted/50 px-6 py-3 text-xs text-muted-foreground">
                            <Lock className="size-3.5" />
                            Diamankan oleh Traz Cloth
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

VerifyOtp.layout = {
    title: 'Verifikasi Nomor WhatsApp',
    description: 'Masukkan kode 6 digit yang kami kirim ke WhatsApp kamu',
};
