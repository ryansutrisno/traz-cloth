import { Form, Head } from '@inertiajs/react';
import { Lock, Mail, Phone, User, UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar" />

            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {/* Strip gradasi atas ala kartu Stitch */}
                <div className="h-1 bg-gradient-to-r from-primary to-primary/30" />

                <div className="flex flex-col gap-6 p-6 sm:p-8">
                    {/* Ikon daftar */}
                    <div className="flex justify-center">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <UserPlus className="size-7" />
                        </span>
                    </div>

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password', 'password_confirmation']}
                        disableWhileProcessing
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama</Label>
                                    <div className="relative">
                                        <User className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="name"
                                            type="text"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="name"
                                            name="name"
                                            placeholder="Nama lengkap"
                                            className="h-11 pl-10"
                                        />
                                    </div>
                                    <InputError
                                        message={errors.name}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="email"
                                                type="email"
                                                required
                                                tabIndex={2}
                                                autoComplete="email"
                                                name="email"
                                                placeholder="email@example.com"
                                                className="h-11 pl-10"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">
                                            Nomor WhatsApp
                                        </Label>
                                        <div className="relative">
                                            <Phone className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="phone"
                                                type="tel"
                                                required
                                                tabIndex={3}
                                                autoComplete="tel"
                                                inputMode="tel"
                                                name="phone"
                                                placeholder="0812xxxxxxx"
                                                aria-describedby="phone-hint"
                                                className="h-11 pl-10"
                                            />
                                        </div>
                                        <InputError message={errors.phone} />
                                    </div>
                                </div>

                                <p
                                    id="phone-hint"
                                    className="-mt-3 text-xs text-muted-foreground"
                                >
                                    Kode verifikasi akan dikirim ke nomor
                                    WhatsApp ini.
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Kata sandi</Label>
                                    <PasswordInput
                                        id="password"
                                        required
                                        tabIndex={4}
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Kata sandi"
                                        passwordrules={passwordRules}
                                        className="h-11"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Konfirmasi kata sandi
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        tabIndex={5}
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="Ulangi kata sandi"
                                        passwordrules={passwordRules}
                                        className="h-11"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 h-12 w-full text-base"
                                    tabIndex={6}
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    Daftar &amp; Kirim Kode
                                </Button>
                            </>
                        )}
                    </Form>

                    <div className="text-center text-sm text-muted-foreground">
                        Sudah punya akun?{' '}
                        <TextLink href={login()} tabIndex={7}>
                            Masuk
                        </TextLink>
                    </div>
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

Register.layout = {
    title: 'Buat akun',
    description: 'Masukkan data kamu untuk membuat akun',
};
