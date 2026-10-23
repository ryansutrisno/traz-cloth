import { Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    LogOut,
    MapPin,
    MessageSquare,
    Package,
    PackageOpen,
    PieChart,
    Shirt,
    TicketPercent,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import {
    dashboard as adminDashboard,
    logout as adminLogout,
} from '@/routes/admin';
import { index as adminCampaignsIndex } from '@/routes/admin/campaigns';
import { index as adminCouponsIndex } from '@/routes/admin/coupons';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as komunikasiIndex } from '@/routes/admin/komunikasi';
import { index as laporanIndex } from '@/routes/admin/laporan';
import { index as readyStockIndex } from '@/routes/admin/ready-stock';
import { index as addressesIndex } from '@/routes/addresses';
import type { AdminUser, NavItem } from '@/types';

const buyerNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Alamat',
        href: addressesIndex.url(),
        icon: MapPin,
        dataTest: 'nav-alamat',
    },
];

function adminNavItems(role: string | undefined): NavItem[] {
    const items: NavItem[] = [
        { title: 'Dashboard', href: adminDashboard(), icon: LayoutGrid },
        { title: 'Kampanye', href: adminCampaignsIndex(), icon: Shirt },
        { title: 'Kupon', href: adminCouponsIndex(), icon: TicketPercent },
        { title: 'Pesanan', href: adminOrdersIndex(), icon: Package },
        {
            title: 'Komunikasi',
            href: komunikasiIndex(),
            icon: MessageSquare,
        },
        {
            title: 'Ready Stock',
            href: readyStockIndex(),
            icon: PackageOpen,
        },
    ];

    if (role === 'admin') {
        items.push({
            title: 'Laporan',
            href: laporanIndex(),
            icon: PieChart,
            dataTest: 'nav-laporan',
        });
    }

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part.charAt(0))
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function AdminNavUser({ admin }: { admin: AdminUser }) {
    const handleLogout = () => {
        router.post(adminLogout.url());
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="sidebar-menu-button"
                        >
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-semibold text-primary-foreground">
                                {getInitials(admin.name)}
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {admin.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground capitalize">
                                    {admin.role}
                                </span>
                            </div>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side="bottom"
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-semibold text-primary-foreground">
                                    {getInitials(admin.name)}
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-medium">
                                        {admin.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {admin.email}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            asChild
                            onClick={handleLogout}
                            data-test="admin-logout-button"
                        >
                            <button className="block w-full cursor-pointer">
                                <LogOut className="mr-2" />
                                Keluar
                            </button>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export function AppSidebar() {
    const { auth } = usePage().props;
    const admin = auth.admin ?? null;
    const isAdmin = admin !== null;

    const items = isAdmin ? adminNavItems(admin?.role) : buyerNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={isAdmin ? adminDashboard() : dashboard()}
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                {!isAdmin && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                {isAdmin && admin ? (
                    <AdminNavUser admin={admin} />
                ) : (
                    <NavUser />
                )}
            </SidebarFooter>
        </Sidebar>
    );
}
