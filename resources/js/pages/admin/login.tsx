import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/admin/login';

export default function AdminLogin() {
    return (
        <>
            <Head title="Admin Login" />
            <div className="flex flex-1 items-center justify-center p-6">
                <div className="w-full max-w-sm">
                    {/* Header */}
                    <div className="mb-8 flex flex-col items-center gap-2 text-center">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <span className="text-sm font-bold">T</span>
                        </div>
                        <h1 className="text-xl font-semibold">
                            Admin Traz Cloth
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Masuk untuk mengelola kampanye
                        </p>
                    </div>

                    <Form {...store.form()} className="flex flex-col gap-6">
                        {({ processing, errors }) => (
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder="admin@trazcloth.id"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Kata sandi</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder="Kata sandi"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
                                    data-test="admin-login-button"
                                >
                                    {processing && <Spinner />}
                                    Masuk
                                </Button>
                            </div>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
