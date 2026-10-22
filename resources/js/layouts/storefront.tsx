import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Heart, ShoppingCart } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as cartIndex } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import {
    about,
    contact,
    dashboard,
    faq,
    home,
    login,
    privacyPolicy,
    shippingPolicy,
    terms,
} from '@/routes';
import { subscribe as newsletterSubscribe } from '@/routes/newsletter';

type Props = {
    title?: string;
    children: ReactNode;
};

function NewsletterForm() {
    const form = useForm<{ email: string }>({ email: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(newsletterSubscribe.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                toast.success('Terima kasih sudah berlangganan!');
            },
            onError: (errors) => {
                toast.error(errors.email ?? 'Gagal berlangganan.');
            },
        });
    };

    return (
        <form
            data-test="newsletter-form"
            onSubmit={handleSubmit}
            className="flex flex-col gap-2"
        >
            <Input
                data-test="newsletter-email-input"
                type="email"
                name="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                placeholder="email kamu"
                required
                aria-label="Email untuk newsletter"
                className="h-9"
            />
            <Button
                type="submit"
                size="sm"
                data-test="newsletter-submit-button"
                disabled={form.processing}
                className="w-full"
            >
                {form.processing ? 'Mengirim...' : 'Berlangganan'}
            </Button>
        </form>
    );
}

export default function StorefrontLayout({ title, children }: Props) {
    const { auth, cartCount } = usePage().props;
    const user = auth.user ?? null;
    const appName = import.meta.env.VITE_APP_NAME || 'Traz Cloth';

    return (
        <>
            <Head title={title} />
            <div className="flex min-h-svh flex-col bg-background">
                {/* Header */}
                <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-4">
                        {/* Logo */}
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-bold tracking-tight"
                        >
                            <span className="text-lg">TRAZ</span>
                        </Link>

                        {/* Nav */}
                        <nav className="flex items-center gap-1 text-sm sm:gap-2">
                            <Link
                                href={campaignsIndex.url()}
                                className="rounded-md px-2 py-1.5 font-medium text-muted-foreground transition-colors hover:text-foreground sm:px-3"
                            >
                                <span className="hidden sm:inline">
                                    Katalog
                                </span>
                                <span className="sm:hidden">Katalog</span>
                            </Link>

                            {/* Cart */}
                            <Link
                                href={cartIndex.url()}
                                className="relative rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground"
                                aria-label="Keranjang"
                            >
                                <ShoppingCart className="size-5" />
                                {cartCount > 0 && (
                                    <span
                                        data-test="cart-count-badge"
                                        className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-semibold text-primary-foreground"
                                    >
                                        {cartCount > 99 ? '99+' : cartCount}
                                    </span>
                                )}
                            </Link>

                            {/* Auth */}
                            {user ? (
                                <div className="flex items-center gap-1 sm:gap-2">
                                    <Link
                                        href={ordersIndex.url()}
                                        className="hidden rounded-md px-2 py-1.5 font-medium text-muted-foreground transition-colors hover:text-foreground sm:inline-block sm:px-3"
                                    >
                                        Pesanan
                                    </Link>
                                    <Link
                                        href={dashboard()}
                                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                    >
                                        <span className="hidden sm:inline">
                                            Akun
                                        </span>
                                        <span className="sm:hidden">
                                            {user.name.charAt(0).toUpperCase()}
                                        </span>
                                    </Link>
                                </div>
                            ) : (
                                <Link
                                    href={login()}
                                    className="rounded-md px-3 py-1.5 font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    Masuk
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Content */}
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-4 md:py-6">
                    {children}
                </main>

                {/* Footer */}
                <footer className="border-t">
                    <div className="mx-auto max-w-6xl px-4 py-8 md:py-10">
                        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            {/* Brand */}
                            <div className="flex flex-col gap-2">
                                <Link
                                    href={home()}
                                    className="text-lg font-bold tracking-tight"
                                >
                                    TRAZ
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    Kaos pre-order edisi terbatas. Desain
                                    custom, diproduksi terbatas, dikirim ke
                                    seluruh Indonesia.
                                </p>
                            </div>

                            {/* Belanja */}
                            <div className="flex flex-col gap-2">
                                <h3 className="text-sm font-semibold">
                                    Belanja
                                </h3>
                                <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
                                    <li>
                                        <Link
                                            href={campaignsIndex.url()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Katalog
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={cartIndex.url()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Keranjang
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            {/* Informasi */}
                            <div className="flex flex-col gap-2">
                                <h3 className="text-sm font-semibold">
                                    Informasi
                                </h3>
                                <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
                                    <li>
                                        <Link
                                            href={terms()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Syarat &amp; Ketentuan
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={privacyPolicy()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Kebijakan Privasi
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={contact()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Hubungi Kami
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={faq()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            FAQ
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={about()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Tentang Kami
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={shippingPolicy()}
                                            className="transition-colors hover:text-foreground"
                                        >
                                            Kebijakan Pengiriman
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            {/* Newsletter */}
                            <div className="flex flex-col gap-2">
                                <h3 className="text-sm font-semibold">
                                    Dapatkan info drop terbaru
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Berlangganan biar tidak ketinggalan drop
                                    berikutnya.
                                </p>
                                <NewsletterForm />
                            </div>
                        </div>

                        {/* Copyright */}
                        <div className="mt-8 border-t pt-4 text-center text-xs text-muted-foreground">
                            &copy; {new Date().getFullYear()} {appName}. Made
                            with{' '}
                            <Heart
                                aria-hidden="true"
                                className="mx-0.5 inline h-3 w-3 fill-red-500 text-red-500"
                            />{' '}
                            by Trazmedia Segoro Digital.
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
