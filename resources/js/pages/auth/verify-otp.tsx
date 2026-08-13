import { Form, Head, router } from '@inertiajs/react';
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
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="phone" value={phone} />
                        <div className="grid gap-6">
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

                            <p className="text-center text-sm text-muted-foreground">
                                Kode dikirim ke WhatsApp{' '}
                                <span className="font-medium text-foreground">
                                    {phone}
                                </span>
                            </p>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                disabled={
                                    processing || code.length < OTP_LENGTH
                                }
                                data-test="verify-otp-button"
                            >
                                {processing && <Spinner />}
                                Verifikasi
                            </Button>

                            <div className="flex flex-col items-center gap-3 text-center text-sm text-muted-foreground">
                                <p>
                                    Tidak menerima kode?{' '}
                                    <button
                                        type="button"
                                        onClick={handleResend}
                                        disabled={resending || countdown > 0}
                                        data-test="resend-otp-button"
                                        className="inline-flex items-center gap-1 font-medium text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:cursor-not-allowed disabled:opacity-50 dark:decoration-neutral-500"
                                    >
                                        {resending && <Spinner />}
                                        {resending
                                            ? 'Mengirim ulang...'
                                            : countdown > 0
                                              ? `Kirim ulang dalam ${countdown}s`
                                              : 'Kirim ulang kode'}
                                    </button>
                                </p>
                                <TextLink href={register()}>
                                    Ganti nomor
                                </TextLink>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyOtp.layout = {
    title: 'Verifikasi Nomor WhatsApp',
    description: 'Masukkan kode 6 digit yang kami kirim ke WhatsApp kamu',
};
