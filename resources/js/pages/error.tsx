import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Bug, Clock, Hourglass, LayoutGrid, LockKeyhole, RotateCcw, ServerCrash, Trophy, Wrench, type LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Status = 403 | 404 | 419 | 429 | 500 | 503;

const COPY: Record<Status, { title: string; text: string; icon: LucideIcon; tone: Tone }> = {
    403: {
        title: 'You don’t have access to this',
        text: 'Your role doesn’t include this page. If you need it, ask an administrator.',
        icon: LockKeyhole,
        tone: 'amber',
    },
    404: {
        title: 'This page wandered off',
        text: 'The link may be old, or the page was moved or deleted. While you’re here, a few bugs got loose…',
        icon: Bug,
        tone: 'pink',
    },
    419: { title: 'This page expired', text: 'It was open too long. Go back and try again.', icon: Hourglass, tone: 'sky' },
    429: { title: 'Too many tries', text: 'Please wait a minute before trying again.', icon: Clock, tone: 'amber' },
    500: {
        title: 'Something broke on our side',
        text: 'It isn’t anything you did. Please try again in a moment; if it keeps happening, tell your administrator.',
        icon: ServerCrash,
        tone: 'red',
    },
    503: { title: 'Back in a few minutes', text: 'The portal is being updated. Please try again shortly.', icon: Wrench, tone: 'slate' },
};

const ROUND_SECONDS = 20;
const BEST_KEY = 'hrms.bug-hunt.best';

type Critter = { id: number; x: number; y: number; angle: number; speed: number; golden: boolean };

function readBest(): number {
    try {
        return Number(window.localStorage.getItem(BEST_KEY)) || 0;
    } catch {
        return 0;
    }
}

function saveBest(score: number) {
    try {
        window.localStorage.setItem(BEST_KEY, String(score));
    } catch {
        // Private mode: the best score simply isn't kept.
    }
}

/**
 * Bug Hunt: bugs crawl across the board for 20 seconds; squash as many as you
 * can. Gold bugs are worth three. Every bug is a button, so it plays by
 * keyboard too (Tab to a bug, Enter to squash).
 */
function BugHunt() {
    const [phase, setPhase] = useState<'idle' | 'playing' | 'over'>('idle');
    const [bugs, setBugs] = useState<Critter[]>([]);
    const [score, setScore] = useState(0);
    const [left, setLeft] = useState(ROUND_SECONDS);
    const [best, setBest] = useState(0);
    const [splats, setSplats] = useState<{ id: number; x: number; y: number }[]>([]);
    const nextId = useRef(1);

    useEffect(() => setBest(readBest()), []);

    const spawn = useCallback((): Critter => {
        const golden = Math.random() < 0.12;
        return {
            id: nextId.current++,
            x: 5 + Math.random() * 90,
            y: 8 + Math.random() * 84,
            angle: Math.random() * Math.PI * 2,
            speed: golden ? 0.9 : 0.35 + Math.random() * 0.45,
            golden,
        };
    }, []);

    const start = () => {
        setScore(0);
        setLeft(ROUND_SECONDS);
        setSplats([]);
        setBugs(Array.from({ length: 5 }, spawn));
        setPhase('playing');
    };

    // Crawl: wander, bounce off the walls, and keep the board populated.
    useEffect(() => {
        if (phase !== 'playing') return;
        const tick = window.setInterval(() => {
            setBugs((all) => {
                const moved = all.map((b) => {
                    let angle = b.angle + (Math.random() - 0.5) * 0.5;
                    let x = b.x + Math.cos(angle) * b.speed;
                    let y = b.y + Math.sin(angle) * b.speed;
                    if (x < 3 || x > 97) angle = Math.PI - angle;
                    if (y < 5 || y > 95) angle = -angle;
                    x = Math.min(97, Math.max(3, x));
                    y = Math.min(95, Math.max(5, y));
                    return { ...b, x, y, angle };
                });
                return moved.length < 4 || (moved.length < 9 && Math.random() < 0.04) ? [...moved, spawn()] : moved;
            });
        }, 50);
        return () => window.clearInterval(tick);
    }, [phase, spawn]);

    useEffect(() => {
        if (phase !== 'playing') return;
        const clock = window.setInterval(() => setLeft((s) => s - 1), 1000);
        return () => window.clearInterval(clock);
    }, [phase]);

    useEffect(() => {
        if (phase === 'playing' && left <= 0) {
            setPhase('over');
            setBugs([]);
            if (score > best) {
                setBest(score);
                saveBest(score);
            }
        }
    }, [left, phase, score, best]);

    const squash = (bug: Critter) => {
        setBugs((all) => all.filter((b) => b.id !== bug.id));
        setScore((s) => s + (bug.golden ? 3 : 1));
        const splat = { id: bug.id, x: bug.x, y: bug.y };
        setSplats((all) => [...all.slice(-12), splat]);
        window.setTimeout(() => setSplats((all) => all.filter((s) => s.id !== splat.id)), 700);
    };

    return (
        <div className="w-full max-w-xl">
            <div className="mb-2 flex items-center justify-between text-sm">
                <span className="font-semibold">Bug Hunt</span>
                <span className="text-muted-foreground inline-flex items-center gap-3 tabular-nums">
                    <span>
                        Score <strong className="text-foreground">{score}</strong>
                    </span>
                    {phase === 'playing' && <span>{left}s</span>}
                    <span className="inline-flex items-center gap-1">
                        <Trophy className="size-3.5" style={{ color: 'var(--status-warning)' }} aria-hidden /> {best}
                    </span>
                </span>
            </div>

            <div
                className="relative h-64 overflow-hidden rounded-xl border sm:h-72"
                style={{
                    background:
                        'repeating-linear-gradient(45deg, color-mix(in oklab, var(--tone-pink) 5%, transparent) 0 12px, transparent 12px 24px)',
                }}
                aria-label="Bug Hunt board"
            >
                {bugs.map((bug) => (
                    <button
                        key={bug.id}
                        type="button"
                        onClick={() => squash(bug)}
                        aria-label={bug.golden ? 'Golden bug, three points' : 'Bug'}
                        className="focus-visible:ring-ring absolute flex size-9 -translate-x-1/2 -translate-y-1/2 cursor-crosshair items-center justify-center rounded-full transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:outline-none"
                        style={{ left: `${bug.x}%`, top: `${bug.y}%` }}
                    >
                        <Bug
                            className="size-7 drop-shadow-sm"
                            style={{
                                color: bug.golden ? 'var(--status-warning)' : 'var(--tone-pink)',
                                transform: `rotate(${(bug.angle * 180) / Math.PI + 90}deg)`,
                            }}
                        />
                    </button>
                ))}

                {splats.map((s) => (
                    <span
                        key={s.id}
                        aria-hidden
                        className="animate-out fade-out-0 zoom-out-50 pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 text-xs font-bold duration-700"
                        style={{ left: `${s.x}%`, top: `${s.y}%`, color: 'var(--status-good)' }}
                    >
                        Fixed!
                    </span>
                ))}

                {phase !== 'playing' && (
                    <div className="bg-background/80 absolute inset-0 flex flex-col items-center justify-center gap-3 text-center backdrop-blur-sm">
                        {phase === 'over' ? (
                            <>
                                <p className="text-2xl font-semibold tabular-nums">{score} bugs fixed</p>
                                <p className="text-muted-foreground text-sm">
                                    {score >= best && score > 0 ? 'A new best! The testers are impressed.' : `Best so far: ${best}`}
                                </p>
                            </>
                        ) : (
                            <p className="text-muted-foreground max-w-xs text-sm">
                                Squash as many bugs as you can in {ROUND_SECONDS} seconds. Gold ones count three.
                            </p>
                        )}
                        <Button onClick={start}>
                            <RotateCcw className="size-4" /> {phase === 'over' ? 'Play again' : 'Start'}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Every error page. Works whether or not anyone is signed in — an unknown
 * address never reaches the middleware that shares the usual page props.
 */
export default function ErrorPage({ status, message }: { status: Status; message?: string | null }) {
    const { props } = usePage<{ auth?: { user?: unknown }; company?: { name?: string } }>();
    const copy = COPY[status] ?? COPY[500];
    const signedIn = Boolean(props.auth?.user);

    return (
        <div className="bg-background flex min-h-svh flex-col items-center px-4 py-10 sm:py-16">
            <Head title={`${status} · ${copy.title}`} />

            <Link href="/" className="mb-10 flex items-center gap-2 text-sm font-semibold">
                <span
                    className="flex size-8 items-center justify-center rounded-lg text-white"
                    style={{ background: 'linear-gradient(135deg, var(--hero-from), var(--hero-to))' }}
                >
                    <AppLogoIcon className="size-5 fill-current" />
                </span>
                {props.company?.name ?? 'HRMS Task'}
            </Link>

            <div className="flex w-full max-w-xl flex-col items-center gap-5 text-center">
                <IconChip icon={copy.icon} tone={copy.tone} />
                <p
                    className="bg-clip-text text-7xl font-black tracking-tight text-transparent tabular-nums sm:text-8xl"
                    style={{ backgroundImage: 'linear-gradient(120deg, var(--hero-from), var(--hero-to))' }}
                >
                    {status}
                </p>
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">{copy.title}</h1>
                    <p className="text-muted-foreground mx-auto max-w-md text-sm">{message ?? copy.text}</p>
                </div>

                <div className="flex flex-wrap justify-center gap-2">
                    <Button variant="outline" onClick={() => window.history.back()}>
                        <ArrowLeft className="size-4" /> Go back
                    </Button>
                    <Button asChild>
                        <Link href={signedIn ? '/dashboard' : '/'}>
                            <LayoutGrid className="size-4" /> {signedIn ? 'Dashboard' : 'Sign in'}
                        </Link>
                    </Button>
                </div>

                {status === 404 && (
                    <div className="mt-6 w-full">
                        <BugHunt />
                    </div>
                )}
            </div>
        </div>
    );
}
