import { Form, Head, Link } from '@inertiajs/react';
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
import { create, index, store } from '@/routes/admin/coupons';

type Props = { types: { value: string; label: string }[] };

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

export default function AdminCouponsCreate({ types }: Props) {
    return (
        <>
            <Head title="Buat Kupon" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Buat Kupon</h1>
                    <Button asChild size="sm" variant="ghost">
                        <Link href={index.url()}>← Kembali</Link>
                    </Button>
                </div>

                <Form
                    {...store.form()}
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
                                        placeholder="HEMAT10"
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="type">Tipe</Label>
                                    <Select
                                        name="type"
                                        defaultValue={types[0]?.value}
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
                                        placeholder="Potongan untuk pelanggan baru"
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
                                        placeholder="10"
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
                                            placeholder="Opsional"
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
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expires_at">Berakhir</Label>
                                    <Input
                                        id="expires_at"
                                        name="expires_at"
                                        type="datetime-local"
                                    />
                                    <InputError message={errors.expires_at} />
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    defaultChecked
                                />
                                <Label htmlFor="is_active">Kupon aktif</Label>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex items-center justify-end gap-2 border-t pt-4">
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
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminCouponsCreate.layout = {
    breadcrumbs: [
        { title: 'Kupon', href: index.url() },
        { title: 'Buat', href: create.url() },
    ],
};
