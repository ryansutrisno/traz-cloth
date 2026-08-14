import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, update } from '@/routes/admin/campaigns';

type CampaignSizeData = {
    size: string;
    price_override: string | null;
    stock: number;
    enabled: boolean;
};

type Campaign = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    image_url: string | null;
    base_price: string;
    cost_per_item: string | null;
    quota: number;
    sold_count: number;
    status: string;
    ready_stock_enabled: boolean;
    sizes: CampaignSizeData[];
};

type Props = {
    campaign: Campaign;
    sizes: string[];
    statuses: string[];
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    sold_out: 'Sold Out',
    disabled: 'Nonaktif',
};

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const textAreaClass =
    'flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function AdminCampaignsEdit({
    campaign,
    sizes,
    statuses,
}: Props) {
    const [title, setTitle] = useState(campaign.title);
    const [slug, setSlug] = useState(campaign.slug);
    // Slug sudah ditetapkan di edit — anggap sudah final kecuali user edit manual.
    const [slugTouched, setSlugTouched] = useState(true);

    const sizeMap = new Map<string, CampaignSizeData>();

    for (const s of campaign.sizes) {
        sizeMap.set(s.size, s);
    }

    const handleTitleChange = (value: string) => {
        setTitle(value);

        if (!slugTouched) {
            setSlug(slugify(value));
        }
    };

    const handleSlugChange = (value: string) => {
        setSlugTouched(true);
        setSlug(value);
    };

    const handleDelete = () => {
        if (!window.confirm('Hapus kampanye ini?')) {
            return;
        }

        router.delete(destroy.url({ campaign: campaign.id }));
    };

    return (
        <>
            <Head title={`Edit: ${campaign.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Edit Kampanye</h1>
                    <Button asChild size="sm" variant="ghost">
                        <Link href={index.url()}>← Kembali</Link>
                    </Button>
                </div>

                <Form
                    {...update.form({ campaign: campaign.id })}
                    data-test="campaign-form"
                    transform={(data) => {
                        if (data.sizes) {
                            for (const size of Object.keys(data.sizes)) {
                                const s = data.sizes[size];
                                s.price_override =
                                    s.price_override === '' ||
                                    s.price_override === undefined
                                        ? null
                                        : s.price_override;
                                s.stock =
                                    s.stock === '' || s.stock === undefined
                                        ? 0
                                        : s.stock;
                                s.enabled = s.enabled === true;
                            }
                        }

                        data.cost_per_item =
                            data.cost_per_item === '' ||
                            data.cost_per_item === undefined
                                ? null
                                : data.cost_per_item;

                        return data;
                    }}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
                                {/* Main */}
                                <div className="grid flex-1 gap-5">
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Judul</Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            type="text"
                                            required
                                            value={title}
                                            onChange={(e) =>
                                                handleTitleChange(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Nama kampanye"
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">Slug</Label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            type="text"
                                            value={slug}
                                            onChange={(e) =>
                                                handleSlugChange(e.target.value)
                                            }
                                            placeholder="otomatis-dari-judul"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Kosongkan untuk otomatis dari judul.
                                        </p>
                                        <InputError message={errors.slug} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Deskripsi
                                        </Label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            className={textAreaClass}
                                            defaultValue={
                                                campaign.description ?? ''
                                            }
                                            placeholder="Ceritakan tentang desain ini..."
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="image_url">
                                            URL Gambar
                                        </Label>
                                        <Input
                                            id="image_url"
                                            name="image_url"
                                            type="url"
                                            defaultValue={
                                                campaign.image_url ?? ''
                                            }
                                            placeholder="https://..."
                                        />
                                        <InputError
                                            message={errors.image_url}
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="base_price">
                                                Harga Dasar
                                            </Label>
                                            <div className="relative">
                                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                    Rp
                                                </span>
                                                <Input
                                                    id="base_price"
                                                    name="base_price"
                                                    type="number"
                                                    min={0}
                                                    step={1000}
                                                    required
                                                    className="pl-9"
                                                    defaultValue={
                                                        campaign.base_price
                                                    }
                                                    placeholder="100000"
                                                />
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Harga dasar ukuran M sebelum
                                                biaya press.
                                            </p>
                                            <InputError
                                                message={errors.base_price}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="quota">Kuota</Label>
                                            <Input
                                                id="quota"
                                                name="quota"
                                                type="number"
                                                min={1}
                                                required
                                                defaultValue={campaign.quota}
                                                placeholder="50"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Kuota global semua ukuran.
                                            </p>
                                            <InputError
                                                message={errors.quota}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="cost_per_item">
                                                Biaya produksi per pcs
                                                (estimasi)
                                            </Label>
                                            <div className="relative">
                                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                    Rp
                                                </span>
                                                <Input
                                                    id="cost_per_item"
                                                    name="cost_per_item"
                                                    type="number"
                                                    min={0}
                                                    step={1000}
                                                    className="pl-9"
                                                    defaultValue={
                                                        campaign.cost_per_item ??
                                                        ''
                                                    }
                                                    placeholder="50000"
                                                />
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Untuk hitung margin di laporan.
                                            </p>
                                            <InputError
                                                message={errors.cost_per_item}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            name="status"
                                            defaultValue={campaign.status}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statuses.map((s) => (
                                                    <SelectItem
                                                        key={s}
                                                        value={s}
                                                    >
                                                        {STATUS_LABELS[s] ?? s}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="ready_stock_enabled"
                                            name="ready_stock_enabled"
                                            defaultChecked={
                                                campaign.ready_stock_enabled
                                            }
                                        />
                                        <Label htmlFor="ready_stock_enabled">
                                            Aktifkan ready stock dari order
                                            batal
                                        </Label>
                                    </div>
                                </div>

                                {/* Sizes */}
                                <div className="flex w-full flex-col gap-4 lg:w-80 lg:shrink-0">
                                    <h2 className="font-medium">
                                        Ukuran & Harga
                                    </h2>
                                    <div className="flex flex-col gap-3">
                                        {sizes.map((size) => {
                                            const existing = sizeMap.get(size);

                                            return (
                                                <div
                                                    key={size}
                                                    className="rounded-lg border p-3"
                                                >
                                                    <div className="mb-3 flex items-center gap-2">
                                                        <Checkbox
                                                            id={`enabled-${size}`}
                                                            name={`sizes[${size}][enabled]`}
                                                            defaultChecked={
                                                                existing?.enabled ??
                                                                false
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`enabled-${size}`}
                                                            className="font-medium"
                                                        >
                                                            {size}
                                                        </Label>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <div className="grid gap-1">
                                                            <Label
                                                                htmlFor={`override-${size}`}
                                                                className="text-xs text-muted-foreground"
                                                            >
                                                                Harga override
                                                            </Label>
                                                            <Input
                                                                id={`override-${size}`}
                                                                name={`sizes[${size}][price_override]`}
                                                                type="number"
                                                                min={0}
                                                                step={1000}
                                                                defaultValue={
                                                                    existing?.price_override ??
                                                                    ''
                                                                }
                                                                placeholder="Otomatis"
                                                            />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                htmlFor={`stock-${size}`}
                                                                className="text-xs text-muted-foreground"
                                                            >
                                                                Stok ready
                                                            </Label>
                                                            <Input
                                                                id={`stock-${size}`}
                                                                name={`sizes[${size}][stock]`}
                                                                type="number"
                                                                min={0}
                                                                defaultValue={
                                                                    existing?.stock ??
                                                                    0
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <InputError message={errors.sizes} />
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex items-center justify-between gap-2 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={handleDelete}
                                >
                                    Hapus Kampanye
                                </Button>
                                <div className="flex items-center gap-2">
                                    <Button asChild variant="outline">
                                        <Link href={index.url()}>Batal</Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        data-test="save-campaign-button"
                                        className="gap-1.5"
                                    >
                                        {processing && <Spinner />}
                                        Simpan
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminCampaignsEdit.layout = {
    breadcrumbs: [{ title: 'Kampanye', href: index.url() }],
};
