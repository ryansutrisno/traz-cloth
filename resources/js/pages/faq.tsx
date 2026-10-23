import { Head } from '@inertiajs/react';

const FAQS: Array<{ question: string; answer: string }> = [
    {
        question: 'Apa itu pre-order limited drop?',
        answer: 'Pre-order limited drop adalah pemesanan desain kaos dalam periode terbatas sebelum produksi dimulai. Kami memproduksi sesuai pesanan yang masuk selama kuota global masih tersedia.',
    },
    {
        question: 'Kapan sebuah kampanye ditutup?',
        answer: 'Kampanye ditutup otomatis saat kuota global habis. Tidak ada deadline tetap, jadi sebaiknya pesan segera sebelum slot produksi penuh.',
    },
    {
        question: 'Apakah DP bisa dikembalikan?',
        answer: 'DP tidak dapat dikembalikan apabila pesanan dibatalkan atau tidak dilunasi sesuai ketentuan. Refund hanya dapat diproses untuk kondisi khusus yang diinformasikan oleh Traz Cloth.',
    },
    {
        question: 'Berapa lama proses produksinya?',
        answer: 'Produksi biasanya membutuhkan sekitar 7–14 hari kerja setelah kampanye ditutup. Estimasi dapat berubah mengikuti jumlah pesanan dan kondisi produksi.',
    },
    {
        question: 'Bagaimana cara melunasi pesanan?',
        answer: 'Buka halaman Pesanan Saya, pilih pesanan yang sudah siap kirim, lalu tekan tombol Bayar Pelunasan. Pembayaran dapat dilakukan melalui metode yang tersedia di checkout.',
    },
    {
        question: 'Apa yang terjadi bila terlambat membayar pelunasan?',
        answer: 'Pelunasan wajib dilakukan dalam 2x24 jam setelah barang siap. Jika lewat dari batas tersebut, pesanan dapat dibatalkan dan DP menjadi hangus. Barang yang tersedia dapat dijual kembali sebagai ready stock.',
    },
    {
        question: 'Bagaimana cara mengecek nomor resi?',
        answer: 'Nomor resi muncul di detail pesanan setelah paket diproses dan kurir berhasil di-book. Pengiriman dilakukan melalui Biteship dengan pilihan kurir seperti JNE, SiCepat, Anteraja, dan lainnya.',
    },
    {
        question: 'Apakah produk bisa ditukar atau diretur?',
        answer: 'Produk pre-order tidak dapat ditukar atau diretur karena dibuat khusus berdasarkan pesanan. Hubungi kami dalam 2x24 jam jika barang rusak atau tidak sesuai, dengan menyertakan foto dan video unboxing.',
    },
];

export default function Faq() {
    return (
        <>
            <Head title="FAQ" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Pertanyaan Umum
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Jawaban singkat seputar pre-order, pembayaran, produksi,
                        dan pengiriman Traz Cloth.
                    </p>
                </div>

                <div data-test="faq-page" className="flex flex-col gap-6">
                    {FAQS.map((item) => (
                        <section
                            key={item.question}
                            className="flex flex-col gap-2"
                        >
                            <h2 className="text-lg font-semibold tracking-tight">
                                {item.question}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {item.answer}
                            </p>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

Faq.layout = {
    title: 'FAQ',
    description: 'Pertanyaan umum seputar Traz Cloth',
};
