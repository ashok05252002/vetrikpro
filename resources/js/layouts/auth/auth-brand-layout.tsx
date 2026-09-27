import CompanyMark from '@/components/company-mark';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bug, FolderKanban, UsersRound, type LucideIcon } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const HIGHLIGHTS: { icon: LucideIcon; title: string; text: string }[] = [
    { icon: UsersRound, title: 'People', text: 'Employee records, onboarding and access in one place.' },
    { icon: FolderKanban, title: 'Projects', text: 'Task boards, requirements and branches per project.' },
    { icon: Bug, title: 'Testing', text: 'Report bugs, assign them and retest in named runs.' },
];

/**
 * Sign-in and account screens: a brand panel in the sidebar's navy on wide
 * screens, the form on its own on narrow ones. The company name leads on both,
 * because it is what tells a visitor which system this is.
 */
export default function AuthBrandLayout({ children, title, description }: AuthLayoutProps) {
    const { company } = usePage<SharedData>().props;

    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="bg-sidebar text-sidebar-foreground relative hidden overflow-hidden p-10 lg:flex lg:flex-col xl:p-14">
                {/* Brand glow and rings, echoing the dashboard hero. Decorative only. */}
                <span
                    aria-hidden
                    className="absolute -top-40 -left-40 size-[32rem] rounded-full opacity-40 blur-3xl"
                    style={{ background: 'radial-gradient(circle, var(--hero-from), transparent 70%)' }}
                />
                <span
                    aria-hidden
                    className="absolute -right-32 -bottom-40 size-[28rem] rounded-full opacity-30 blur-3xl"
                    style={{ background: 'radial-gradient(circle, var(--hero-to), transparent 70%)' }}
                />
                <span aria-hidden className="absolute top-1/3 -right-24 size-72 rounded-full border-[32px] border-white/[0.04]" />

                <Link href={route('home')} className="relative flex items-center gap-3">
                    <CompanyMark surface="dark" className="size-10 rounded-xl" />
                    <span className="text-lg font-semibold text-white">{company.name}</span>
                </Link>

                <div className="relative my-auto max-w-md py-12">
                    <h2 className="text-3xl leading-tight font-semibold tracking-tight text-white xl:text-4xl">
                        Your people and your projects, in one workspace.
                    </h2>
                    <ul className="mt-10 space-y-6">
                        {HIGHLIGHTS.map(({ icon: Icon, title, text }) => (
                            <li key={title} className="flex gap-4">
                                <span className="bg-sidebar-accent border-sidebar-border flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                    <Icon className="size-5 text-indigo-300" />
                                </span>
                                <div>
                                    <p className="font-medium text-white">{title}</p>
                                    <p className="text-sm">{text}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-xs text-white/50">
                    © {new Date().getFullYear()} {company.name}
                </p>
            </aside>

            <main className="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <div className="w-full max-w-sm">
                    <Link href={route('home')} className="mb-10 flex items-center gap-3 lg:hidden">
                        <CompanyMark surface="auto" className="size-9" />
                        <span className="font-semibold">{company.name}</span>
                    </Link>

                    <div className="mb-8 space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground text-sm">{description}</p>
                    </div>

                    {children}
                </div>
            </main>
        </div>
    );
}
