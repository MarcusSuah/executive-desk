import { Form, Head } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    CalendarDays,
    CheckSquare,
    FileText,
    Lock,
    Mail,
    Quote,
    Settings,
    User,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AppearanceToggle } from '@/components/appearance-toggle';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { redirect as googleRedirect } from '@/routes/auth/google';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    googleEnabled: boolean;
    supportEmail: string | null;
    heroImage: string | null;
};

const FEATURES: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: CalendarDays,
        title: 'Manage Your Schedule',
        text: 'Appointments, meetings, and commitments in one place.',
    },
    {
        icon: CheckSquare,
        title: 'Stay on Top of Tasks',
        text: 'Priorities, deadlines, and progress tracking.',
    },
    {
        icon: FileText,
        title: 'Prepare with Confidence',
        text: 'Presentations, documents, and meeting preparation.',
    },
    {
        icon: Bell,
        title: 'Never Miss What Matters',
        text: 'Email, SMS, and push reminders with customizable preferences.',
    },
];

const HIGHLIGHTS = [
    'Every meeting arrives with its agenda, its documents, and a reminder at the moment you choose.',
    'A daily briefing in your time zone: today’s appointments, deadlines, and what changed overnight.',
    'Assistants prepare, executives decide. Everyone sees exactly what they need, and nothing more.',
];

const ROLES: {
    icon: LucideIcon;
    title: string;
    text: string;
    tone: string;
}[] = [
    {
        icon: User,
        title: 'Executives',
        text: 'View schedule, tasks, briefings, and notifications.',
        tone: 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300',
    },
    {
        icon: Users,
        title: 'Assistants',
        text: 'Manage calendar, tasks, and prepare briefings.',
        tone: 'bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300',
    },
    {
        icon: Settings,
        title: 'Administrators',
        text: 'Manage users, settings, and system health.',
        tone: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300',
    },
];

function GoogleIcon() {
    return (
        <svg viewBox="0 0 48 48" className="size-5" aria-hidden>
            <path
                fill="#FFC107"
                d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"
            />
            <path
                fill="#FF3D00"
                d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"
            />
            <path
                fill="#4CAF50"
                d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"
            />
            <path
                fill="#1976D2"
                d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"
            />
        </svg>
    );
}

/**
 * Rotates through the highlight statements on the brand panel.
 */
function useRotatingIndex(length: number, intervalMs: number) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        const timer = window.setInterval(
            () => setIndex((current) => (current + 1) % length),
            intervalMs,
        );

        return () => window.clearInterval(timer);
    }, [length, intervalMs]);

    return [index, setIndex] as const;
}

export default function Login({
    status,
    canResetPassword,
    googleEnabled,
    supportEmail,
    heroImage,
}: Props) {
    const [highlight, setHighlight] = useRotatingIndex(HIGHLIGHTS.length, 7000);

    return (
        <>
            <Head title="Sign in" />

            {/* One screen, no scrolling: secondary content hides on short viewports. */}
            <div className="grid min-h-svh lg:h-svh lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:overflow-hidden">
                {/* Brand panel */}
                <aside
                    className="relative hidden overflow-hidden bg-[#071029] text-white lg:flex lg:flex-col"
                    style={
                        heroImage
                            ? {
                                  backgroundImage: `linear-gradient(90deg, rgba(7,16,41,0.95) 0%, rgba(7,16,41,0.85) 45%, rgba(7,16,41,0.35) 100%), url(${heroImage})`,
                                  backgroundSize: 'cover',
                                  backgroundPosition: 'center',
                              }
                            : undefined
                    }
                >
                    {!heroImage && (
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0"
                        >
                            <div className="absolute inset-0 bg-gradient-to-br from-[#0b1a3d] via-[#071029] to-[#030712]" />
                            <div className="absolute -top-40 right-[-10%] size-[32rem] rounded-full bg-blue-600/25 blur-3xl" />
                            <div className="absolute right-[10%] bottom-[-20%] size-[24rem] rounded-full bg-orange-400/15 blur-3xl" />
                            <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:48px_48px]" />
                        </div>
                    )}

                    <div className="relative flex flex-1 flex-col px-10 py-7 xl:px-12">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-blue-600/15 text-blue-400">
                                <AppLogoIcon className="size-6 fill-current" />
                            </span>
                            <div>
                                <p className="text-lg leading-tight font-semibold tracking-tight">
                                    Executive Desk
                                </p>
                                <p className="text-xs text-slate-300">
                                    Plan Smarter. Achieve More.
                                </p>
                            </div>
                        </div>

                        <div className="my-auto max-w-lg py-6">
                            <h1 className="text-3xl leading-tight font-semibold tracking-tight xl:text-4xl">
                                Your Executive
                                <span className="block text-blue-400">
                                    Assistant Platform
                                </span>
                            </h1>
                            <p className="mt-3 max-w-md text-sm leading-relaxed text-slate-300 [@media(max-height:640px)]:hidden">
                                A secure, all-in-one workspace for managing your
                                schedule, tasks, presentations, and
                                notifications.
                            </p>

                            <ul className="mt-6 max-w-md space-y-3.5">
                                {FEATURES.map((feature) => (
                                    <li
                                        key={feature.title}
                                        className="flex items-center gap-3.5"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10 backdrop-blur">
                                            <feature.icon
                                                className="size-5 text-blue-400"
                                                aria-hidden
                                            />
                                        </span>
                                        <span>
                                            <span className="block text-sm font-semibold">
                                                {feature.title}
                                            </span>
                                            <span className="block text-xs leading-snug text-slate-300">
                                                {feature.text}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="max-w-md [@media(max-height:720px)]:hidden">
                            <div className="border-t border-white/10 pt-5">
                                <div className="flex gap-3" aria-live="polite">
                                    <Quote
                                        className="size-5 shrink-0 rotate-180 fill-slate-500 text-slate-500"
                                        aria-hidden
                                    />
                                    <p className="min-h-10 text-sm leading-relaxed text-slate-200 italic">
                                        {HIGHLIGHTS[highlight]}
                                    </p>
                                </div>
                            </div>
                            <div
                                className="mt-4 flex gap-2"
                                role="tablist"
                                aria-label="Highlights"
                            >
                                {HIGHLIGHTS.map((text, index) => (
                                    <button
                                        key={text}
                                        type="button"
                                        role="tab"
                                        aria-selected={index === highlight}
                                        aria-label={`Highlight ${index + 1}`}
                                        onClick={() => setHighlight(index)}
                                        className={cn(
                                            'size-2 rounded-full transition-colors',
                                            index === highlight
                                                ? 'bg-blue-500'
                                                : 'bg-white/25 hover:bg-white/40',
                                        )}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Sign-in panel */}
                <main className="relative flex flex-col bg-slate-50 px-4 py-4 sm:px-8 lg:overflow-y-auto dark:bg-background">
                    <div className="flex justify-end">
                        <AppearanceToggle />
                    </div>

                    <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-2">
                        <div className="mb-5 flex items-center justify-center gap-3">
                            <AppLogoIcon className="size-9 fill-blue-600 dark:fill-blue-400" />
                            <div>
                                <p className="text-2xl leading-tight font-semibold tracking-tight">
                                    Executive Desk
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Secure. Organized. Always Ahead.
                                </p>
                            </div>
                        </div>

                        <div className="rounded-xl border bg-card p-6 shadow-sm sm:px-7">
                            <h2 className="text-xl font-semibold tracking-tight">
                                Welcome Back
                            </h2>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                Sign in to your Executive Desk account.
                            </p>

                            {status && (
                                <p className="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {status}
                                </p>
                            )}

                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="mt-5 flex flex-col gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="email">
                                                Email Address
                                            </Label>
                                            <div className="relative">
                                                <Mail
                                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                    aria-hidden
                                                />
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoFocus
                                                    tabIndex={1}
                                                    autoComplete="email"
                                                    placeholder="you@example.com"
                                                    className="h-10 pl-9"
                                                />
                                            </div>
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <div className="flex items-center">
                                                <Label htmlFor="password">
                                                    Password
                                                </Label>
                                                {canResetPassword && (
                                                    <TextLink
                                                        href={request()}
                                                        className="ml-auto text-xs text-blue-600 no-underline hover:underline dark:text-blue-400"
                                                        tabIndex={5}
                                                    >
                                                        Forgot password?
                                                    </TextLink>
                                                )}
                                            </div>
                                            <div className="relative">
                                                <Lock
                                                    className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                                    aria-hidden
                                                />
                                                <PasswordInput
                                                    id="password"
                                                    name="password"
                                                    required
                                                    tabIndex={2}
                                                    autoComplete="current-password"
                                                    placeholder="Enter your password"
                                                    className="h-10 pl-9"
                                                />
                                            </div>
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="flex items-center gap-2.5">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                tabIndex={3}
                                            />
                                            <Label
                                                htmlFor="remember"
                                                className="text-xs font-normal text-muted-foreground"
                                            >
                                                Keep me signed in on this device
                                            </Label>
                                        </div>

                                        <Button
                                            type="submit"
                                            className="h-10 w-full bg-blue-600 text-white hover:bg-blue-700"
                                            tabIndex={4}
                                            disabled={processing}
                                            data-test="login-button"
                                        >
                                            {processing && <Spinner />}
                                            Sign In
                                            {!processing && (
                                                <ArrowRight className="size-4" />
                                            )}
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {googleEnabled && (
                                <>
                                    <div className="my-4 flex items-center gap-3 text-xs text-muted-foreground">
                                        <span className="h-px flex-1 bg-border" />
                                        or
                                        <span className="h-px flex-1 bg-border" />
                                    </div>
                                    <Button
                                        variant="outline"
                                        className="h-10 w-full"
                                        asChild
                                    >
                                        <a href={googleRedirect.url()}>
                                            <GoogleIcon />
                                            Sign in with Google
                                        </a>
                                    </Button>
                                </>
                            )}

                            <p className="mt-4 text-center text-xs text-muted-foreground">
                                Need help?{' '}
                                {supportEmail ? (
                                    <a
                                        href={`mailto:${supportEmail}`}
                                        className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        Contact your administrator
                                    </a>
                                ) : (
                                    <span className="font-medium text-foreground">
                                        Contact your administrator
                                    </span>
                                )}
                            </p>
                        </div>

                        <div className="mt-5 [@media(max-height:820px)]:hidden">
                            <p className="mb-2.5 text-center text-xs font-medium text-muted-foreground">
                                Access for
                            </p>
                            <ul className="grid grid-cols-3 gap-2.5">
                                {ROLES.map((role) => (
                                    <li
                                        key={role.title}
                                        className="rounded-lg border bg-card px-2 py-3 text-center"
                                    >
                                        <span
                                            className={cn(
                                                'mx-auto flex size-8 items-center justify-center rounded-full',
                                                role.tone,
                                            )}
                                        >
                                            <role.icon
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                        <p className="mt-1.5 text-xs font-semibold">
                                            {role.title}
                                        </p>
                                        <p className="mt-0.5 text-[11px] leading-snug text-muted-foreground">
                                            {role.text}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    <p className="pt-2 text-center text-[11px] text-muted-foreground">
                        © {new Date().getFullYear()} Executive Desk. Accounts
                        are created by your administrator.
                    </p>
                </main>
            </div>
        </>
    );
}
