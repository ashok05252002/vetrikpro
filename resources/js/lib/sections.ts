import type { Tone } from '@/components/viz/icon-chip';
import {
    Boxes,
    Briefcase,
    Building2,
    Contact,
    FlaskConical,
    FolderKanban,
    GitPullRequest,
    IdCard,
    LayoutGrid,
    ListChecks,
    Mail,
    ReceiptIndianRupee,
    Settings2,
    ShieldCheck,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';

/**
 * One icon and one colour per kind of thing, used everywhere it appears —
 * the sidebar, page titles, tiles, cards — so a colour always means the same
 * section. Git is GitHub's own purple; money is green; mail is blue.
 *
 * Colour marks identity only: every icon sits beside its words, and the
 * status hues (red, amber) stay reserved for urgency.
 */
export const SECTIONS = {
    dashboard: { icon: LayoutGrid, tone: 'indigo' },
    tasks: { icon: ListChecks, tone: 'violet' },
    projects: { icon: FolderKanban, tone: 'sky' },
    testing: { icon: FlaskConical, tone: 'pink' },
    git: { icon: GitPullRequest, tone: 'git' },
    employees: { icon: IdCard, tone: 'teal' },
    people: { icon: UsersRound, tone: 'teal' },
    roles: { icon: ShieldCheck, tone: 'teal' },
    departments: { icon: Building2, tone: 'teal' },
    designations: { icon: Briefcase, tone: 'teal' },
    accounts: { icon: ReceiptIndianRupee, tone: 'green' },
    invoices: { icon: ReceiptIndianRupee, tone: 'green' },
    customers: { icon: Contact, tone: 'green' },
    products: { icon: Boxes, tone: 'green' },
    config: { icon: Settings2, tone: 'slate' },
    mail: { icon: Mail, tone: 'blue' },
} satisfies Record<string, { icon: LucideIcon; tone: Tone }>;

export type SectionKey = keyof typeof SECTIONS;
