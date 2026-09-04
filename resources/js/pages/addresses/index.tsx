import { Head, router, useForm } from '@inertiajs/react';
import { Briefcase, Home, MapPin, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/addresses';

type Address = {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    subdistrict: string;
    postal_code: string;
    full_address: string;
    is_default: boolean;
};

type Props = {
    addresses: Address[];
};

type FormValues = {
    label: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    subdistrict: string;
    postal_code: string;
    full_address: string;
    is_default: boolean;
};

const EMPTY_FORM: FormValues = {
    label: '',
    recipient_name: '',
    phone: '',
    province: '',
    city: '',
    district: '',
    subdistrict: '',
    postal_code: '',
    full_address: '',
    is_default: false,
};

const textAreaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

function labelIcon(label: string): typeof Home {
    const normalized = label.toLowerCase();

    if (normalized.includes('rumah') || normalized.includes('home')) {
        return Home;
    }

    if (normalized.includes('kantor') || normalized.includes('studio')) {
        return Briefcase;
    }

    return MapPin;
}

export default function AddressesIndex({ addresses }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [showForm, setShowForm] = useState(false);

    const form = useForm<FormValues>(EMPTY_FORM);

    const startCreate = () => {
        setEditingId(null);
        form.setData(EMPTY_FORM);
        form.reset();
        setShowForm(true);
    };

    const startEdit = (address: Address) => {
        setEditingId(address.id);
        const values: FormValues = {
            label: address.label,
            recipient_name: address.recipient_name,
            phone: address.phone,
            province: address.province,
            city: address.city,
            district: address.district,
            subdistrict: address.subdistrict,
            postal_code: address.postal_code,
            full_address: address.full_address,
            is_default: address.is_default,
        };
        form.setData(values);
        form.reset();
        setShowForm(true);
    };

    const cancelForm = () => {
        setShowForm(false);
        setEditingId(null);
        form.setData(EMPTY_FORM);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const onSuccess = () => {
            toast.success(
                editingId ? 'Alamat diperbarui' : 'Alamat ditambahkan',
            );
            cancelForm();
        };

        const onError = () => {
            toast.error('Periksa kembali isian kamu');
        };

        if (editingId) {
            form.put(update.url({ address: editingId }), {
                preserveScroll: true,
                onSuccess,
                onError,
            });
        } else {
            form.post(store.url(), {
                preserveScroll: true,
                onSuccess,
                onError,
            });
        }
    };

    const handleSetDefault = (address: Address) => {
        router.patch(
            update.url({ address: address.id }),
            { is_default: true },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Alamat utama diperbarui'),
                onError: () => toast.error('Gagal mengubah alamat utama'),
            },
        );
    };

    const handleDelete = (address: Address) => {
        if (!window.confirm('Hapus alamat ini?')) {
            return;
        }

        router.delete(destroy.url({ address: address.id }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Alamat dihapus'),
            onError: () => toast.error('Gagal menghapus alamat'),
        });
    };

    return (
        <>
            <Head title="Alamat Saya" />
            <div className="flex flex-col gap-8">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Alamat Saya
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Kelola alamat pengiriman untuk checkout lebih cepat.
                    </p>
                </div>

                {/* Form tambah/edit */}
                {showForm && (
                    <Card className="gap-4 p-4 md:p-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">
                                {editingId ? 'Edit Alamat' : 'Tambah Alamat'}
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={cancelForm}
                            >
                                Batal
                            </Button>
                        </div>

                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="label">Label</Label>
                                    <Input
                                        id="label"
                                        value={form.data.label}
                                        onChange={(e) =>
                                            form.setData(
                                                'label',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Rumah"
                                    />
                                    <InputError message={form.errors.label} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="recipient_name">
                                        Nama Penerima
                                    </Label>
                                    <Input
                                        id="recipient_name"
                                        value={form.data.recipient_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'recipient_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Nama penerima"
                                    />
                                    <InputError
                                        message={form.errors.recipient_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        No. WhatsApp / Telepon
                                    </Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0812xxxxxxx"
                                    />
                                    <InputError message={form.errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="postal_code">
                                        Kode Pos
                                    </Label>
                                    <Input
                                        id="postal_code"
                                        value={form.data.postal_code}
                                        onChange={(e) =>
                                            form.setData(
                                                'postal_code',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="12345"
                                    />
                                    <InputError
                                        message={form.errors.postal_code}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="province">Provinsi</Label>
                                    <Input
                                        id="province"
                                        value={form.data.province}
                                        onChange={(e) =>
                                            form.setData(
                                                'province',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Jawa Barat"
                                    />
                                    <InputError
                                        message={form.errors.province}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="city">Kota/Kabupaten</Label>
                                    <Input
                                        id="city"
                                        value={form.data.city}
                                        onChange={(e) =>
                                            form.setData('city', e.target.value)
                                        }
                                        placeholder="Bandung"
                                    />
                                    <InputError message={form.errors.city} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="district">Kecamatan</Label>
                                    <Input
                                        id="district"
                                        value={form.data.district}
                                        onChange={(e) =>
                                            form.setData(
                                                'district',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Coblong"
                                    />
                                    <InputError
                                        message={form.errors.district}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subdistrict">
                                        Kelurahan/Desa
                                    </Label>
                                    <Input
                                        id="subdistrict"
                                        value={form.data.subdistrict}
                                        onChange={(e) =>
                                            form.setData(
                                                'subdistrict',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Lebak Gede"
                                    />
                                    <InputError
                                        message={form.errors.subdistrict}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="full_address">
                                    Alamat Lengkap
                                </Label>
                                <textarea
                                    id="full_address"
                                    value={form.data.full_address}
                                    onChange={(e) =>
                                        form.setData(
                                            'full_address',
                                            e.target.value,
                                        )
                                    }
                                    className={textAreaClass}
                                    placeholder="Jl. Contoh No. 123, RT 01/RW 02..."
                                />
                                <InputError
                                    message={form.errors.full_address}
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_default"
                                    checked={form.data.is_default}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_default',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_default">
                                    Jadikan alamat utama
                                </Label>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={cancelForm}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    className="gap-1.5"
                                    data-test="save-address-button"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    Simpan
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}

                {/* Grid alamat */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {addresses.map((address) => {
                        const Icon = labelIcon(address.label);

                        return (
                            <div
                                key={address.id}
                                data-test="address-card"
                                className="relative flex flex-col rounded-xl border bg-card p-6 transition-shadow hover:shadow-md"
                            >
                                {address.is_default && (
                                    <span className="absolute top-4 right-4 inline-flex items-center rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                        Utama
                                    </span>
                                )}

                                <div className="mb-4 flex items-center gap-3">
                                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="text-lg font-semibold tracking-tight">
                                        {address.label}
                                    </h3>
                                </div>

                                <div className="mb-6 flex flex-col gap-1 text-sm text-muted-foreground">
                                    <p className="font-semibold text-foreground">
                                        {address.recipient_name}
                                    </p>
                                    <p>{address.full_address}</p>
                                    <p>
                                        {address.district},{' '}
                                        {address.subdistrict}
                                    </p>
                                    <p>
                                        {address.city}, {address.province}{' '}
                                        {address.postal_code}
                                    </p>
                                    <p className="pt-2">{address.phone}</p>
                                </div>

                                <div className="mt-auto flex items-center gap-3 border-t pt-4">
                                    <button
                                        type="button"
                                        data-test="edit-address-button"
                                        onClick={() => startEdit(address)}
                                        className="text-sm font-medium text-primary transition-colors hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <span className="text-border">|</span>
                                    <button
                                        type="button"
                                        data-test="delete-address-button"
                                        onClick={() => handleDelete(address)}
                                        className="flex items-center gap-1 text-sm font-medium text-destructive transition-colors hover:underline"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Hapus
                                    </button>
                                    {!address.is_default && (
                                        <button
                                            type="button"
                                            data-test="set-default-address-button"
                                            onClick={() =>
                                                handleSetDefault(address)
                                            }
                                            className="ml-auto text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
                                        >
                                            Set Utama
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {/* Kartu tambah alamat — dashed */}
                    {!showForm && (
                        <button
                            type="button"
                            data-test="add-address-button"
                            onClick={startCreate}
                            className="group flex min-h-[250px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 p-6 transition-colors hover:border-primary hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span className="mb-4 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                <Plus className="size-6" />
                            </span>
                            <h3 className="text-lg font-semibold group-hover:text-primary">
                                Tambah Alamat Baru
                            </h3>
                            <p className="mt-2 max-w-[220px] text-center text-sm text-muted-foreground">
                                Simpan alamat lain untuk mempercepat checkout.
                            </p>
                        </button>
                    )}
                </div>

                {/* Info kosong */}
                {addresses.length === 0 && !showForm && (
                    <p className="text-center text-sm text-muted-foreground">
                        Belum ada alamat tersimpan. Tambahkan alamat pertama
                        kamu lewat kartu di atas.
                    </p>
                )}
            </div>
        </>
    );
}

AddressesIndex.layout = {
    title: 'Alamat Saya',
    description: 'Kelola alamat pengiriman kamu',
};
