import { Head } from '@inertiajs/react';

const SECTIONS: Array<{ title: string; body: string }> = [
    {
        title: 'Tentang Traz Cloth',
        body: 'Traz Cloth adalah toko kaos pre-order dengan konsep limited drop. Kami menghadirkan desain pilihan dalam jumlah terbatas agar setiap rilisan terasa spesial dan tidak diproduksi berlebihan.',
    },
    {
        title: 'Cara Kami Berkarya',
        body: 'Setiap desain dibuka untuk pre-order selama kuota global masih tersedia. Setelah kuota terpenuhi, kampanye ditutup dan pesanan masuk ke proses produksi sesuai jumlah order yang berhasil dikonfirmasi.',
    },
    {
        title: 'Lebih Sedikit Terbuang',
        body: 'Model pre-order membantu kami memproduksi berdasarkan permintaan. Jika ada pesanan yang dibatalkan karena tidak dilunasi, stok siap kirim dapat kami tawarkan kembali sebagai ready stock.',
    },
    {
        title: 'Komitmen Kami',
        body: 'Kami berusaha menjaga kualitas bahan, hasil cetak, dan pengalaman belanja yang jelas dari checkout sampai pengiriman. Jika ada kendala, tim Traz Cloth siap membantu melalui halaman Hubungi Kami.',
    },
];

export default function About() {
    return (
        <>
            <Head title="Tentang Kami" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Tentang Traz Cloth
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Kaos limited drop yang dibuat berdasarkan pesanan kamu.
                    </p>
                </div>

                <div data-test="about-page" className="flex flex-col gap-6">
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

About.layout = {
    title: 'Tentang Kami',
    description: 'Tentang Traz Cloth',
};
