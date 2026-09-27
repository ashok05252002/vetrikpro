import { CardTitle } from '@/components/ui/card';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import type { LucideIcon } from 'lucide-react';

/** A card title with a small icon chip in its section's tone — the standard card heading. */
export default function CardHeading({ icon, tone, children }: { icon: LucideIcon; tone: Tone; children: React.ReactNode }) {
    return (
        <CardTitle className="flex items-center gap-2.5 text-base">
            <IconChip icon={icon} tone={tone} size="xs" />
            {children}
        </CardTitle>
    );
}
