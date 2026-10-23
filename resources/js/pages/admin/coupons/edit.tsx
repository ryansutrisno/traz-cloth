import { Form, Head, Link, router } from '@inertiajs/react';
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
import { destroy, index, update } from '@/routes/admin/coupons';

type Coupon = {
    id: number;
    code: string;
    description: string | null;
    type: string;
    value: number | string;
    min_purchase: number | string | null;
    max_discount: number | string | null;
    usage_limit: number | null;
    per_user_limit: number | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
};

type Props = { coupon: Coupon; types: { value: string; label: string }[] };

const fields = [
    ['min_purchase', 'Min. Belanja', 'Nominal minimum belanja (opsional).'],
    ['max_discount', 'Maks. Diskon', 'Hanya berlaku untuk tipe persentase.'],
    ['usage_limit', 'Batas Pemakaian', 'Kosongkan untuk tanpa batas.'],
    [
        'per_user_limit',
        'Batas per Pengguna',
        'Batas pemakaian setiap pengguna.',
    ],
] as const;

function dateTimeValue(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value.slice(0, 16)
        : date.toISOString().slice(0, 16);
}

export default function AdminCouponsEdit({ coupon, types }: Props) {
    const handleDelete = () => {
        if (window.confirm('Hapus kupon ini?')) {
            router.delete(destroy.url({ coupon: coupon.id }));
        }
    };

    return (
        <>
            <Head title={`Edit: ${coupon.code}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Edit Kupon</h1>
                    <Button asChild size="sm" variant="ghost">
                        <Link href={index.url()}>← Kembali</Link>
                    </Button>
                </div>

                <Form
                    {...update.form({ coupon: coupon.id })}
                    data-test="coupon-form"
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Kode</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        className="uppercase"
                                        defaultValue={coupon.code}
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="type">Tipe</Label>
                                    <Select
                                        name="type"
                                        defaultValue={coupon.type}
                                    >
                                        <SelectTrigger
                                            id="type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Pilih tipe" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {types.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2 lg:col-span-2">
                                    <Label htmlFor="description">
                                        Deskripsi
                                    </Label>
                                    <Input
                                        id="description"
                                        name="description"
                                        defaultValue={coupon.description ?? ''}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="value">Nilai</Label>
                                    <Input
                                        id="value"
                                        name="value"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        defaultValue={coupon.value}
                                    />
                                    <InputError message={errors.value} />
                                </div>
                                {fields.map(([name, label, hint]) => (
                                    <div key={name} className="grid gap-2">
                                        <Label htmlFor={name}>{label}</Label>
                                        <Input
                                            id={name}
                                            name={name}
                                            type="number"
                                            min="0"
                                            step={
                                                name === 'usage_limit' ||
                                                name === 'per_user_limit'
                                                    ? '1'
                                                    : '1000'
                                            }
                                            defaultValue={coupon[name] ?? ''}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {hint}
                                        </p>
                                        <InputError message={errors[name]} />
                                    </div>
                                ))}
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Mulai</Label>
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                        defaultValue={dateTimeValue(
                                            coupon.starts_at,
                                        )}
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expires_at">Berakhir</Label>
                                    <Input
                                        id="expires_at"
                                        name="expires_at"
                                        type="datetime-local"
                                        defaultValue={dateTimeValue(
                                            coupon.expires_at,
                                        )}
                                    />
                                    <InputError message={errors.expires_at} />
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    defaultChecked={coupon.is_active}
                                />
                                <Label htmlFor="is_active">Kupon aktif</Label>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex items-center justify-between gap-2 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={handleDelete}
                                >
                                    Hapus Kupon
                                </Button>
                                <div className="flex items-center gap-2">
                                    <Button asChild variant="outline">
                                        <Link href={index.url()}>Batal</Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        data-test="save-coupon-button"
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

AdminCouponsEdit.layout = {
    breadcrumbs: [{ title: 'Kupon', href: index.url() }],
};
