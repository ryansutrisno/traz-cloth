import { cn } from '@/lib/utils';

type Props = {
    src: string;
    alt: string;
    badge?: string;
    soldOut?: boolean;
    className?: string;
};

export default function CampaignMockup({
    src,
    alt,
    badge,
    soldOut = false,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'relative aspect-[4/5] overflow-hidden rounded-lg bg-muted',
                soldOut && 'opacity-60',
                className,
            )}
        >
            <img
                src={src}
                alt={alt}
                loading="lazy"
                className="size-full object-cover"
            />
            {/* Gradient overlay bawah tipis */}
            <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/30 to-transparent" />

            {/* Badge kiri-atas */}
            {badge && (
                <span className="absolute top-1.5 left-1.5 rounded-full bg-primary px-2 py-0.5 text-[10px] font-medium text-primary-foreground shadow-sm">
                    {badge}
                </span>
            )}

            {/* Sold Out overlay */}
            {soldOut && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <span className="rounded-md bg-destructive px-3 py-1 text-xs font-semibold text-white shadow-md">
                        Sold Out
                    </span>
                </div>
            )}
        </div>
    );
}
