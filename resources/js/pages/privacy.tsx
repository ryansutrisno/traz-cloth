import { Head } from '@inertiajs/react';

const SECTIONS: Array<{ title: string; body: string }> = [
    {
        title: 'Data yang Kami Kumpulkan',
        body: 'Saat bertransaksi, kami mengumpulkan data berikut: nama lengkap, alamat email, nomor WhatsApp, dan alamat pengiriman. Data pembayaran diproses langsung oleh Midtrans dan tidak disimpan di server kami.',
    },
    {
        title: 'Penggunaan Data',
        body: 'Data kamu digunakan untuk memproses pesanan, mengatur pengiriman, mengirim notifikasi status pesanan via WhatsApp, serta mengirim info drop terbaru jika kamu berlangganan newsletter. Kami tidak menjual data kamu ke pihak ketiga.',
    },
    {
        title: 'Penyimpanan & Keamanan',
        body: 'Data disimpan di server yang terlindungi dengan enkripsi. Akses data terbatas hanya untuk tim yang berwenang. Kami menyimpan data selama akun aktif atau sesuai ketentuan hukum yang berlaku.',
    },
    {
        title: 'Hak Kamu',
        body: 'Kamu berhak meminta akses, perbaikan, atau penghapusan data pribadi yang kami simpan. Permintaan dapat dikirim melalui halaman Hubungi Kami. Kami akan memproses permintaan dalam 14 hari kerja.',
    },
    {
        title: 'Kontak',
        body: 'Jika ada pertanyaan terkait kebijakan privasi, silakan hubungi kami melalui email halo@traz-cloth.test atau WhatsApp +62 812-3456-7890. Tim kami siap membantu pada jam operasional Senin–Sabtu 09.00–18.00 WIB.',
    },
];

export default function Privacy() {
    return (
        <>
            <Head title="Kebijakan Privasi" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Kebijakan Privasi
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Bagaimana Traz Cloth mengelola dan melindungi data kamu.
                    </p>
                </div>

                <div data-test="privacy-page" className="flex flex-col gap-6">
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

Privacy.layout = {
    title: 'Kebijakan Privasi',
    description: 'Bagaimana Traz Cloth mengelola data kamu',
};
