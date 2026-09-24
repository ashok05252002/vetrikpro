import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { company } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <div className="mb-1 flex h-9 w-9 items-center justify-center overflow-hidden rounded-md">
                                {company.logo ? (
                                    <img src={company.logo} alt="" className="size-9 object-contain" />
                                ) : (
                                    <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                                )}
                            </div>
                            <span className="sr-only">{company.name}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            {/* The company name is the point of the sign-in screen: it says which system this is. */}
                            <p className="text-muted-foreground text-sm font-medium">{company.name}</p>
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
