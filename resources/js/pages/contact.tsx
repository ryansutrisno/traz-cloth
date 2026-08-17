import { Head } from '@inertiajs/react';
import { Clock, Mail, MessageCircle } from 'lucide-react';
import { Card } from '@/components/ui/card';

type ContactCard = {
    icon: typeof Mail;
    label: string;
    value: string;
    hint?: string;
};

const CONTACTS: ContactCard[] = [
    {
        icon: Mail,
        label: 'Email',
        value: 'halo@traz-cloth.test',
        hint: 'Balas dalam 1x24 jam kerja',
    },
    {
        icon: MessageCircle,
        label: 'WhatsApp',
        value: '+62 812-3456-7890',
        hint: 'Paling cepat direspons',
    },
    {
        icon: Clock,
        label: 'Jam Operasional',
        value: 'Senin–Sabtu, 09.00–18.00 WIB',
    },
];

export default function Contact() {
    return (
        <>
            <Head title="Hubungi Kami" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Hubungi Kami
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Tim Traz Cloth siap membantu pertanyaan kamu.
                    </p>
                </div>

                <div
                    data-test="contact-page"
                    className="grid grid-cols-1 gap-3 sm:grid-cols-3"
                >
                    {CONTACTS.map((contact) => (
                        <Card key={contact.label} className="gap-2 p-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <contact.icon className="size-4 text-muted-foreground" />
                            </span>
                            <h2 className="text-sm font-semibold">
                                {contact.label}
                            </h2>
                            <p className="text-sm font-medium tabular-nums">
                                {contact.value}
                            </p>
                            {contact.hint && (
                                <p className="text-xs text-muted-foreground">
                                    {contact.hint}
                                </p>
                            )}
                        </Card>
                    ))}
                </div>

                <div className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                    Chat via WhatsApp paling cepat ditanggapi. Sertakan nomor
                    pesanan kalau ada, ya.
                </div>
            </div>
        </>
    );
}

Contact.layout = {
    title: 'Hubungi Kami',
    description: 'Tim Traz Cloth siap membantu',
};
