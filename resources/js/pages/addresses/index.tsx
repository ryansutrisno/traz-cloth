import { Head, router, useForm } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Alamat Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola alamat pengiriman kamu
                        </p>
                    </div>
                    {!showForm && (
                        <Button
                            onClick={startCreate}
                            className="gap-1.5"
                            data-test="add-address-button"
                        >
                            <Plus className="size-4" />
                            Tambah Alamat
                        </Button>
                    )}
                </div>

                {/* Form */}
                {showForm && (
                    <Card className="gap-4 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">
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

                {/* List */}
                {addresses.length === 0 && !showForm ? (
                    <div className="flex flex-col items-center justify-center gap-4 py-20 text-center">
                        <MapPin className="size-12 text-muted-foreground/40" />
                        <p className="text-lg font-medium">Belum ada alamat</p>
                        <p className="text-sm text-muted-foreground">
                            Tambahkan alamat pengiriman untuk checkout lebih
                            cepat.
                        </p>
                        <Button
                            onClick={startCreate}
                            className="gap-1.5"
                            data-test="add-address-button"
                        >
                            <Plus className="size-4" />
                            Tambah Alamat
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {addresses.map((address) => (
                            <Card
                                key={address.id}
                                data-test="address-card"
                                className="gap-3 p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary">
                                                {address.label}
                                            </Badge>
                                            {address.is_default && (
                                                <Badge>Utama</Badge>
                                            )}
                                        </div>
                                        <p className="font-medium">
                                            {address.recipient_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {address.full_address}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {address.province}, {address.city},{' '}
                                            {address.district},{' '}
                                            {address.subdistrict}{' '}
                                            {address.postal_code}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {address.phone}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {!address.is_default && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                data-test="set-default-address-button"
                                                onClick={() =>
                                                    handleSetDefault(address)
                                                }
                                            >
                                                Set Utama
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            data-test="edit-address-button"
                                            onClick={() => startEdit(address)}
                                        >
                                            <Pencil className="size-4" />
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive"
                                            data-test="delete-address-button"
                                            onClick={() =>
                                                handleDelete(address)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AddressesIndex.layout = {
    breadcrumbs: [{ title: 'Alamat Saya', href: '#', icon: MapPin }],
};
