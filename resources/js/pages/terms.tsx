import { Head } from '@inertiajs/react';

const SECTIONS: Array<{ title: string; body: string }> = [
    {
        title: 'Pendahuluan',
        body: 'Selamat datang di Traz Cloth. Dengan mengakses dan melakukan pemesanan di platform kami, kamu dianggap setuju dengan syarat dan ketentuan berikut. Mohon dibaca dengan saksama sebelum bertransaksi.',
    },
    {
        title: 'Pre-Order & Pembayaran',
        body: 'Setiap desain diproduksi dalam kuota terbatas (pre-order limited drop). Pembayaran dapat dilakukan dengan DP minimal 50% saat checkout atau langsung lunas. DP hangus total jika order dibatalkan oleh pembeli. Pelunasan sisa wajib dilakukan dalam 2x24 jam setelah barang dinyatakan siap kirim.',
    },
    {
        title: 'Pengiriman',
        body: 'Pesanan dikirim menggunakan layanan kurir pihak ketiga (Biteship). Ongkir dihitung saat checkout berdasarkan alamat pengiriman. Nomor resi akan tersedia di halaman Pesanan Saya setelah kurir di-book. Traz Cloth tidak bertanggung jawab atas keterlambatan yang disebabkan oleh pihak kurir.',
    },
    {
        title: 'Hak Kekayaan Intelektual',
        body: 'Seluruh desain yang ditampilkan di Traz Cloth adalah milik pembuatnya. Dilarang melakukan reproduksi, menyalin, atau mendistribusikan ulang desain tanpa izin tertulis dari pemilik hak. Pelanggaran dapat dikenakan tindakan hukum.',
    },
    {
        title: 'Pembatalan & Pengembalian',
        body: 'Order dapat dibatalkan sebelum produksi dimulai. DP yang sudah dibayarkan tidak dapat dikembalikan (hangus). Pengembalian dana hanya berlaku untuk pesanan lunas yang belum masuk produksi, dan diproses dalam 7 hari kerja. Pesanan yang sudah masuk produksi tidak dapat dibatalkan.',
    },
    {
        title: 'Batasan Tanggung Jawab',
        body: 'Traz Cloth berkomitmen mengirimkan produk sesuai deskripsi. Kami tidak bertanggung jawab atas perbedaan warna akibat pengaturan layar perangkat, kerusakan akibat kelalaian kurir, atau force majeure. Klaim kerusakan harap disertai bukti foto dan diajukan dalam 2x24 jam setelah pesanan diterima.',
    },
];

export default function Terms() {
    return (
        <>
            <Head title="Syarat & Ketentuan" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Syarat &amp; Ketentuan
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Syarat dan ketentuan penggunaan layanan Traz Cloth.
                    </p>
                </div>

                <div data-test="terms-page" className="flex flex-col gap-6">
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

Terms.layout = {
    title: 'Syarat & Ketentuan',
    description: 'Syarat & Ketentuan Traz Cloth',
};
