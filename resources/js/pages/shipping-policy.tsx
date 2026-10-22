import { Head } from '@inertiajs/react';

const SECTIONS: Array<{ title: string; body: string }> = [
    {
        title: 'Kurir dan Layanan Pengiriman',
        body: 'Pesanan Traz Cloth dikirim melalui Biteship. Pilihan kurir dapat mencakup JNE, SiCepat, Anteraja, dan layanan lain yang tersedia berdasarkan alamat serta cakupan pengiriman.',
    },
    {
        title: 'Perhitungan Ongkir',
        body: 'Ongkos kirim dihitung otomatis saat checkout berdasarkan alamat tujuan, berat paket, dan layanan kurir yang dipilih. Pastikan alamat dan nomor telepon yang diberikan sudah benar.',
    },
    {
        title: 'Kapan Pesanan Dikirim?',
        body: 'Setelah pembayaran lunas dan produk siap, pesanan akan diproses untuk pengiriman. Nomor resi akan muncul di detail pesanan setelah order pengiriman berhasil dibuat melalui Biteship.',
    },
    {
        title: 'Pelacakan dan Kendala Pengiriman',
        body: 'Gunakan nomor resi pada halaman Pesanan Saya untuk melacak paket melalui layanan kurir terkait. Keterlambatan atau perubahan status setelah paket diserahkan menjadi tanggung jawab pihak kurir, tetapi tim kami tetap akan membantu menindaklanjuti kendala yang dilaporkan.',
    },
    {
        title: 'Pesanan Batal dan Ready Stock',
        body: 'Jika pesanan dibatalkan karena pelunasan tidak dilakukan dalam 2x24 jam setelah barang siap, DP menjadi hangus. Produk yang sudah tersedia dapat dijual kembali sebagai ready stock sesuai ketersediaan.',
    },
];

export default function ShippingPolicy() {
    return (
        <>
            <Head title="Kebijakan Pengiriman" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Kebijakan Pengiriman
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Informasi kurir, ongkos kirim, dan pelacakan pesanan
                        Traz Cloth.
                    </p>
                </div>

                <div
                    data-test="shipping-policy-page"
                    className="flex flex-col gap-6"
                >
                    {SECTIONS.map((section) => (
                        <section
                            key={section.title}
                            className="flex flex-col gap-2"
                        >
                            <h2 className="text-lg font-semibold tracking-tight">
                                {section.title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {section.body}
                            </p>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

ShippingPolicy.layout = {
    title: 'Kebijakan Pengiriman',
    description: 'Kebijakan pengiriman Traz Cloth',
};
