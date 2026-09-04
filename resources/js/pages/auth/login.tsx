import { Form, Head } from '@inertiajs/react';
import { Lock, LogIn, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk" />

            {/* @chisel-passkeys */}
            <PasskeyVerify />
            {/* @end-chisel-passkeys */}

            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {/* Strip gradasi atas ala kartu Stitch */}
                <div className="h-1 bg-gradient-to-r from-primary to-primary/30" />

                <div className="flex flex-col gap-6 p-6 sm:p-8">
                    {/* Ikon masuk */}
                    <div className="flex justify-center">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <LogIn className="size-7" />
                        </span>
                    </div>

                    {status && (
                        <div className="rounded-lg bg-green-50 p-3 text-center text-sm font-medium text-green-700 dark:bg-green-950/30 dark:text-green-400">
                            {status}
                        </div>
                    )}

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        disableWhileProcessing
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                            className="h-11 pl-10"
                                        />
                                    </div>
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center">
                                        <Label htmlFor="password">
                                            Kata sandi
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                                tabIndex={5}
                                            >
                                                Lupa kata sandi?
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Kata sandi"
                                        className="h-11"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">Ingat saya</Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 h-12 w-full text-base"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    Masuk
                                </Button>
                            </>
                        )}
                    </Form>

                    {/* @chisel-registration */}
                    <div className="text-center text-sm text-muted-foreground">
                        Belum punya akun?{' '}
                        <TextLink href={register()} tabIndex={6}>
                            Daftar
                        </TextLink>
                    </div>
                    {/* @end-chisel-registration */}
                </div>

                {/* Catatan keamanan */}
                <div className="flex items-center justify-center gap-1.5 border-t bg-muted/50 px-6 py-3 text-xs text-muted-foreground">
                    <Lock className="size-3.5" />
                    Diamankan oleh Traz Cloth
                </div>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Masuk ke akun kamu',
    description: 'Masukkan email dan kata sandi untuk melanjutkan belanja',
};
