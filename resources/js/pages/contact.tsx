import { Head } from '@inertiajs/react';
import { Clock, HelpCircle, Mail, MessageCircle } from 'lucide-react';

type ContactCard = {
    icon: typeof Mail;
    label: string;
    value: string;
    href?: string;
    hint?: string;
    tone: 'primary' | 'green' | 'neutral';
};

const CONTACTS: ContactCard[] = [
    {
        icon: Mail,
        label: 'Email',
        value: 'halo@traz-cloth.test',
        href: 'mailto:halo@traz-cloth.test',
        hint: 'Dibalas dalam 1x24 jam kerja',
        tone: 'primary',
    },
    {
        icon: MessageCircle,
        label: 'WhatsApp',
        value: '+62 812-3456-7890',
        hint: 'Paling cepat direspons',
        tone: 'green',
    },
    {
        icon: Clock,
        label: 'Jam Operasional',
        value: 'Senin–Sabtu, 09.00–18.00 WIB',
        tone: 'neutral',
    },
];

const QUICK_ANSWERS: Array<{ q: string; a: string }> = [
    {
        q: 'Kapan pesanan saya dicetak?',
        a: 'Setelah pembayaran (DP atau lunas) dikonfirmasi, pesanan langsung masuk antrian produksi.',
    },
    {
        q: 'Berapa ongkir pengirimannya?',
        a: 'Ongkir dihitung otomatis saat checkout berdasarkan alamat dan berat pesanan.',
    },
    {
        q: 'Apakah DP bisa dikembalikan?',
        a: 'DP hangus total jika order dibatalkan. Kamu bisa lanjut pelunasan kapan saja sebelum tenggat.',
    },
];

function iconToneClass(tone: ContactCard['tone']): string {
    if (tone === 'primary') {
        return 'bg-primary/10 text-primary';
    }

    if (tone === 'green') {
        return 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-400';
    }

    return 'bg-muted text-muted-foreground';
}

export default function Contact() {
    return (
        <>
            <Head title="Hubungi Kami" />

            <div className="flex flex-col gap-8 md:gap-12">
                {/* Header tengah */}
                <header className="mx-auto flex max-w-2xl flex-col gap-3 text-center">
                    <h1 className="text-3xl font-bold tracking-tight text-primary md:text-5xl">
                        Hubungi Kami
                    </h1>
                    <p className="text-base text-muted-foreground md:text-lg">
                        Ada pertanyaan soal pesanan, pengiriman, atau desain?
                        Tim Traz Cloth siap membantu kamu.
                    </p>
                </header>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Dukungan langsung */}
                    <div className="flex flex-col gap-6 rounded-xl border bg-card p-6 shadow-sm md:p-8 lg:col-span-7">
                        <div>
                            <h2 className="text-xl font-semibold tracking-tight">
                                Dukungan Langsung
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Tim kami siap membantu Senin sampai Sabtu,
                                09.00–18.00 WIB.
                            </p>
                        </div>

                        <div className="flex flex-col gap-5">
                            {CONTACTS.map((contact) => (
                                <div
                                    key={contact.label}
                                    className="flex items-start gap-4"
                                >
                                    <span
                                        className={`flex size-10 shrink-0 items-center justify-center rounded-full ${iconToneClass(contact.tone)}`}
                                    >
                                        <contact.icon className="size-5" />
                                    </span>
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-sm font-semibold">
                                            {contact.label}
                                        </span>
                                        {contact.href ? (
                                            <a
                                                href={contact.href}
                                                className="text-base font-medium text-primary hover:underline"
                                            >
                                                {contact.value}
                                            </a>
                                        ) : (
                                            <span className="text-base font-medium">
                                                {contact.value}
                                            </span>
                                        )}
                                        {contact.hint && (
                                            <span className="text-xs text-muted-foreground">
                                                {contact.hint}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                            Chat via WhatsApp paling cepat ditanggapi. Sertakan
                            nomor pesanan kalau ada, ya.
                        </div>
                    </div>

                    {/* Pertanyaan cepat */}
                    <div className="flex flex-col gap-6 rounded-xl border bg-card p-6 shadow-sm md:p-8 lg:col-span-5">
                        <h2 className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <HelpCircle className="size-5 text-primary" />
                            Pertanyaan Cepat
                        </h2>

                        <div className="flex flex-col divide-y">
                            {QUICK_ANSWERS.map((item) => (
                                <div
                                    key={item.q}
                                    className="flex flex-col gap-1.5 py-4 first:pt-0 last:pb-0"
                                >
                                    <span className="text-sm font-semibold">
                                        {item.q}
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        {item.a}
                                    </p>
                                </div>
                            ))}
                        </div>

                        <p className="mt-auto text-xs text-muted-foreground">
                            Jawaban lengkap tersedia di bagian Pertanyaan Umum
                            di halaman utama.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

Contact.layout = {
    title: 'Hubungi Kami',
    description: 'Tim Traz Cloth siap membantu',
};
