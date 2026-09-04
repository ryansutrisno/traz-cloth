import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, Mail, MailCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa Kata Sandi" />

            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {/* Strip gradasi atas ala kartu Stitch */}
                <div className="h-1 bg-gradient-to-r from-primary to-primary/30" />

                <div className="flex flex-col gap-6 p-6 sm:p-8">
                    {/* Ikon reset */}
                    <div className="flex justify-center">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <MailCheck className="size-7" />
                        </span>
                    </div>

                    {status && (
                        <div className="rounded-lg bg-green-50 p-3 text-center text-sm font-medium text-green-700 dark:bg-green-950/30 dark:text-green-400">
                            {status}
                        </div>
                    )}

                    <div className="flex flex-col gap-5">
                        <Form {...email.form()}>
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Alamat Email
                                        </Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                autoComplete="off"
                                                autoFocus
                                                placeholder="nama@example.com"
                                                className="h-11 pl-10"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <Button
                                        className="h-12 w-full text-base"
                                        disabled={processing}
                                        data-test="email-password-reset-link-button"
                                    >
                                        {processing && <Spinner />}
                                        Kirim Tautan Reset
                                    </Button>
                                </>
                            )}
                        </Form>

                        <div className="text-center">
                            <TextLink
                                href={login()}
                                className="inline-flex items-center gap-1.5"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali ke Masuk
                            </TextLink>
                        </div>
                    </div>
                </div>

                {/* Catatan keamanan */}
                <div className="flex items-center justify-center gap-1.5 border-t bg-muted/50 px-6 py-3 text-xs text-muted-foreground">
                    Tautan reset berlaku selama 60 menit setelah dikirim.
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa Kata Sandi',
    description: 'Masukkan email kamu untuk menerima tautan reset kata sandi',
};
