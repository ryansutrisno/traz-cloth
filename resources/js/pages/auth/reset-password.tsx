import { Form, Head } from '@inertiajs/react';
import { KeyRound, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Atur Ulang Kata Sandi" />

            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {/* Strip gradasi atas ala kartu Stitch */}
                <div className="h-1 bg-gradient-to-r from-primary to-primary/30" />

                <div className="flex flex-col gap-6 p-6 sm:p-8">
                    {/* Ikon reset */}
                    <div className="flex justify-center">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <KeyRound className="size-7" />
                        </span>
                    </div>

                    <Form
                        {...update.form()}
                        transform={(data) => ({ ...data, token, email })}
                        resetOnSuccess={['password', 'password_confirmation']}
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
                                            autoComplete="email"
                                            value={email}
                                            readOnly
                                            className="h-11 pl-10"
                                        />
                                    </div>
                                    <InputError
                                        message={errors.email}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Kata sandi baru
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        autoComplete="new-password"
                                        autoFocus
                                        placeholder="Kata sandi baru"
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
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        placeholder="Ulangi kata sandi baru"
                                        passwordrules={passwordRules}
                                        className="h-11"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                        className="mt-2"
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 h-12 w-full text-base"
                                    disabled={processing}
                                    data-test="reset-password-button"
                                >
                                    {processing && <Spinner />}
                                    Reset Kata Sandi
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                {/* Catatan keamanan */}
                <div className="flex items-center justify-center gap-1.5 border-t bg-muted/50 px-6 py-3 text-xs text-muted-foreground">
                    Setelah berhasil, kamu bisa masuk dengan kata sandi baru.
                </div>
            </div>
        </>
    );
}

ResetPassword.layout = {
    title: 'Atur Ulang Kata Sandi',
    description: 'Buat kata sandi baru untuk akun kamu',
};
