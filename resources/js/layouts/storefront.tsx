import { Head, Link, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import type { ReactNode } from 'react';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as cartIndex } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import { dashboard, login } from '@/routes';

type Props = {
    title?: string;
    children: ReactNode;
};

export default function StorefrontLayout({ title, children }: Props) {
    const { auth, cartCount } = usePage().props;
    const user = auth.user ?? null;
    const appName = import.meta.env.VITE_APP_NAME || 'TRAZ';

    return (
        <>
            <Head title={title} />
            <div className="flex min-h-svh flex-col bg-background">
                {/* Header */}
                <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-4">
                        {/* Logo */}
                        <Link
                            href={campaignsIndex.url()}
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
                                        className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground"
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
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row">
                        <p>
                            &copy; {new Date().getFullYear()} {appName}.
                            Pre-order kaos lokal.
                        </p>
                        <p className="text-xs">
                            Dibuat dengan Laravel + Inertia + React
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
