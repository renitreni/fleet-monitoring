import { Head, Link, usePage } from '@inertiajs/react';
import BrandLogo from '@/Components/BrandLogo';
import ThemeToggle from '@/Components/ThemeToggle';

const Arrow = () => (
    <svg viewBox="0 0 24 24" fill="none" className="h-5 w-5" aria-hidden="true">
        <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="square" />
    </svg>
);

const StatusIcon = ({ children }) => (
    <span className="grid h-11 w-11 shrink-0 place-items-center border border-white/10 bg-white/[0.04] text-[#ee2b24]">
        {children}
    </span>
);

export default function Welcome() {
    const user = usePage().props.auth?.user;

    return (
        <>
            <Head title="Drive ready">
                <meta
                    name="description"
                    content="Intelligent car maintenance tracking, reminders, and engine oil recommendations."
                />
            </Head>

            <div className="editorial-shell min-h-screen bg-[var(--background)] text-[var(--text)] selection:bg-[var(--accent)] selection:text-white">
                <header className="hero-header absolute inset-x-0 top-0 z-40 border-b border-white/10">
                    <nav className="mx-auto flex h-20 max-w-[1480px] items-center justify-between px-5 sm:px-8 lg:px-12">
                        <a href="#top" aria-label="Motologiq home">
                            <BrandLogo wordmarkClassName="text-white" />
                        </a>
                        <div className="hidden items-center gap-9 text-[12px] font-bold uppercase tracking-[0.18em] text-white/55 lg:flex">
                            <a href="#platform" className="transition hover:text-white">
                                Platform
                            </a>
                            <a href="#intelligence" className="transition hover:text-white">
                                Intelligence
                            </a>
                            <a href="#garage" className="transition hover:text-white">
                                Your garage
                            </a>
                        </div>
                        <div className="flex items-center gap-4">
                            <ThemeToggle />
                            {user ? (
                                <Link
                                    href="/dashboard"
                                    className="group flex items-center gap-3 bg-[#ee2b24] px-5 py-3 text-xs font-black uppercase tracking-[0.14em] transition hover:bg-white hover:text-black"
                                >
                                    My Dashboard <Arrow />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="hidden text-sm font-semibold text-white/65 transition hover:text-white sm:block"
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="group flex items-center gap-3 bg-[#ee2b24] px-5 py-3 text-xs font-black uppercase tracking-[0.14em] transition hover:bg-white hover:text-black"
                                    >
                                        Get started <Arrow />
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main id="top">
                    <section className="hero-stage relative isolate min-h-[900px] overflow-hidden border-b border-white/10">
                        <img
                            src="/images/motologiq-hero.png"
                            alt="Graphite performance car in a night-time pit lane"
                            className="absolute inset-0 h-full w-full object-cover object-[66%_center]"
                        />
                        <div className="absolute inset-0 bg-[linear-gradient(90deg,#090b0d_0%,rgba(9,11,13,.93)_27%,rgba(9,11,13,.35)_64%,rgba(9,11,13,.06)_100%)]" />
                        <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(9,11,13,.55)_0%,transparent_25%,transparent_70%,#090b0d_100%)]" />
                        <div className="motologiq-grid absolute inset-0 opacity-25" />

                        <div className="relative mx-auto flex min-h-[900px] max-w-[1480px] items-end px-5 pb-20 pt-36 sm:px-8 lg:items-center lg:px-12 lg:pb-0">
                            <div className="max-w-[760px]">
                                <div className="flex items-center gap-3 text-[11px] font-black uppercase tracking-[0.26em] text-white/55">
                                    <span className="h-px w-10 bg-[#ee2b24]" /> Intelligent vehicle care
                                </div>
                                <h1 className="mt-7 text-[clamp(4.6rem,9vw,9.6rem)] font-black uppercase leading-[0.78] tracking-[-0.075em]">
                                    Drive
                                    <br />
                                    <span className="text-outline">ready.</span>
                                </h1>
                                <p className="mt-9 max-w-xl text-lg leading-8 text-white/65 sm:text-xl">
                                    Your car tells a story in kilometers, oil, and time. Motologiq reads the signals and
                                    keeps you ahead of what comes next.
                                </p>
                                <div className="mt-10 flex flex-col gap-5 sm:flex-row sm:items-center">
                                    <Link
                                        href="/register"
                                        className="group flex w-fit items-center gap-6 bg-white px-7 py-4 text-sm font-black uppercase tracking-[0.12em] text-black transition hover:bg-[#ee2b24] hover:text-white"
                                    >
                                        Add your car <Arrow />
                                    </Link>
                                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-white/35">
                                        No card · Setup in 2 minutes
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="absolute bottom-0 right-0 hidden w-[380px] border-l border-t border-white/10 bg-black/55 p-6 backdrop-blur-xl xl:block">
                            <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.2em] text-white/40">
                                <span>Vehicle status</span>
                                <span className="flex items-center gap-2 text-[#72e69b]">
                                    <i className="h-1.5 w-1.5 animate-pulse rounded-full bg-[#72e69b]" /> Ready
                                </span>
                            </div>
                            <div className="mt-5 flex items-end justify-between">
                                <div>
                                    <p className="text-sm text-white/45">2019 Honda Civic</p>
                                    <p className="mt-1 text-2xl font-bold tracking-tight">7,420 km</p>
                                </div>
                                <p className="font-mono text-xs text-white/35">MQL-019</p>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-white/10 bg-[#090b0d]">
                        <div className="mx-auto grid max-w-[1480px] divide-y divide-white/10 px-5 sm:px-8 md:grid-cols-3 md:divide-x md:divide-y-0 lg:px-12">
                            {[
                                ['01', 'Track', 'Service history and mileage, always in sync.'],
                                ['02', 'Predict', 'Know what is due before the warning light.'],
                                ['03', 'Protect', 'The right oil spec for your engine and climate.'],
                            ].map(([number, title, copy]) => (
                                <div
                                    key={number}
                                    className="flex gap-6 py-8 md:px-7 md:first:pl-0 md:last:pr-0 lg:py-10"
                                >
                                    <span className="font-mono text-xs text-[#ee2b24]">/{number}</span>
                                    <div>
                                        <h2 className="text-lg font-bold uppercase tracking-[-0.01em]">{title}</h2>
                                        <p className="mt-2 text-sm leading-6 text-white/40">{copy}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section id="platform" className="px-5 py-24 sm:px-8 lg:px-12 lg:py-36">
                        <div className="mx-auto max-w-[1384px]">
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-end">
                                <div>
                                    <p className="eyebrow">The cockpit</p>
                                    <h2 className="mt-6 text-5xl font-black uppercase leading-[0.9] tracking-[-0.055em] sm:text-7xl">
                                        Everything your car needs.
                                        <br />
                                        <span className="text-white/20">Nothing it doesn’t.</span>
                                    </h2>
                                </div>
                                <p className="max-w-lg text-lg leading-8 text-white/45 lg:justify-self-end">
                                    A clean command center for every vehicle you own. See health, maintenance, and
                                    recommendations without digging through receipts or guessing at intervals.
                                </p>
                            </div>

                            <div className="relative mt-16 overflow-hidden border border-white/10 bg-[#101316]">
                                <div className="flex h-12 items-center justify-between border-b border-white/10 px-5">
                                    <span className="font-mono text-[10px] uppercase tracking-[0.18em] text-white/30">
                                        Garage / Overview
                                    </span>
                                    <span className="flex gap-1.5">
                                        <i className="h-1.5 w-1.5 rounded-full bg-white/15" />
                                        <i className="h-1.5 w-1.5 rounded-full bg-white/15" />
                                        <i className="h-1.5 w-1.5 rounded-full bg-[#ee2b24]" />
                                    </span>
                                </div>
                                <div className="grid lg:grid-cols-[1.25fr_.75fr]">
                                    <div className="border-b border-white/10 p-6 sm:p-10 lg:border-b-0 lg:border-r">
                                        <div className="flex flex-col justify-between gap-8 sm:flex-row sm:items-start">
                                            <div>
                                                <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#ee2b24]">
                                                    Daily driver
                                                </p>
                                                <h3 className="mt-3 text-4xl font-bold tracking-[-0.04em]">
                                                    Honda Civic RS
                                                </h3>
                                                <p className="mt-2 font-mono text-xs text-white/30">
                                                    2019 · 1.5L TURBO · CVT
                                                </p>
                                            </div>
                                            <span className="w-fit border border-[#72e69b]/25 bg-[#72e69b]/10 px-3 py-2 text-[10px] font-black uppercase tracking-[0.16em] text-[#72e69b]">
                                                All systems ready
                                            </span>
                                        </div>
                                        <div className="mt-16 grid grid-cols-2 gap-px bg-white/10 sm:grid-cols-4">
                                            {[
                                                ['42,180', 'Odometer / km'],
                                                ['84%', 'Oil life'],
                                                ['12', 'Days to service'],
                                                ['0W-20', 'Recommended'],
                                            ].map(([value, label]) => (
                                                <div key={label} className="bg-[#101316] py-5 pr-3">
                                                    <p className="text-2xl font-bold tracking-tight sm:text-3xl">
                                                        {value}
                                                    </p>
                                                    <p className="mt-2 text-[9px] font-bold uppercase tracking-[0.16em] text-white/30">
                                                        {label}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-10">
                                            <div className="flex justify-between text-[10px] font-bold uppercase tracking-[0.15em] text-white/35">
                                                <span>Service interval</span>
                                                <span>6,720 / 8,000 km</span>
                                            </div>
                                            <div className="mt-4 h-1 bg-white/10">
                                                <div className="h-full w-[84%] bg-[#ee2b24]" />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="p-6 sm:p-10">
                                        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-white/30">
                                            Next actions
                                        </p>
                                        <div className="mt-7 space-y-3">
                                            <div className="flex items-center gap-4 border border-white/10 bg-white/[0.025] p-4">
                                                <StatusIcon>◒</StatusIcon>
                                                <div className="min-w-0 grow">
                                                    <p className="text-sm font-bold">Engine oil</p>
                                                    <p className="mt-1 text-xs text-white/35">Due in 12 days</p>
                                                </div>
                                                <span className="text-xs font-bold text-[#ee2b24]">84%</span>
                                            </div>
                                            <div className="flex items-center gap-4 border border-white/10 bg-white/[0.025] p-4">
                                                <StatusIcon>⌁</StatusIcon>
                                                <div className="min-w-0 grow">
                                                    <p className="text-sm font-bold">Mileage update</p>
                                                    <p className="mt-1 text-xs text-white/35">
                                                        Last updated 4 days ago
                                                    </p>
                                                </div>
                                                <Arrow />
                                            </div>
                                            <div className="flex items-center gap-4 border border-white/10 bg-white/[0.025] p-4">
                                                <StatusIcon>✦</StatusIcon>
                                                <div className="min-w-0 grow">
                                                    <p className="text-sm font-bold">Motologiq intelligence</p>
                                                    <p className="mt-1 text-xs text-white/35">Oil match is ready</p>
                                                </div>
                                                <Arrow />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="intelligence"
                        className="relative overflow-hidden border-y border-white/10 bg-[#ee2b24] px-5 py-24 text-white sm:px-8 lg:px-12 lg:py-32"
                    >
                        <div className="speed-lines absolute inset-0 opacity-20" />
                        <div className="relative mx-auto grid max-w-[1384px] gap-14 lg:grid-cols-[.8fr_1.2fr] lg:items-center">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.22em] text-black/45">
                                    Motologiq intelligence
                                </p>
                                <p className="mt-8 font-mono text-7xl font-bold tracking-[-0.07em] sm:text-9xl">
                                    0W<span className="text-black/25">—</span>20
                                </p>
                                <p className="mt-5 text-sm font-bold uppercase tracking-[0.14em]">
                                    Full synthetic · API SP
                                </p>
                            </div>
                            <div>
                                <h2 className="text-5xl font-black uppercase leading-[0.9] tracking-[-0.055em] sm:text-7xl">
                                    The right oil.
                                    <br />
                                    For this engine.
                                    <br />
                                    <span className="text-black/25">In this climate.</span>
                                </h2>
                                <p className="mt-8 max-w-xl text-lg leading-8 text-white/70">
                                    Recommendations built from your vehicle, country, and driving conditions—then saved
                                    so the answer is ready when you are.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section id="garage" className="px-5 py-24 sm:px-8 lg:px-12 lg:py-36">
                        <div className="mx-auto max-w-[1384px]">
                            <div className="grid gap-16 lg:grid-cols-[.9fr_1.1fr]">
                                <div>
                                    <p className="eyebrow">Built for ownership</p>
                                    <h2 className="mt-6 text-5xl font-black uppercase leading-[0.9] tracking-[-0.055em] sm:text-7xl">
                                        Your complete garage memory.
                                    </h2>
                                    <p className="mt-8 max-w-lg text-lg leading-8 text-white/45">
                                        Every change, every kilometer, every next step. Motologiq turns scattered car
                                        care into a clear record you can trust.
                                    </p>
                                </div>
                                <div className="divide-y divide-white/10 border-y border-white/10">
                                    {[
                                        [
                                            '01',
                                            'Automatic service timing',
                                            'Next due dates and mileage calculated from every oil change.',
                                        ],
                                        [
                                            '02',
                                            'Precision reminders',
                                            'A daily check that only speaks up when your car needs attention.',
                                        ],
                                        [
                                            '03',
                                            'Multi-car clarity',
                                            'One account, every car, each with its own timeline and status.',
                                        ],
                                    ].map(([number, title, copy]) => (
                                        <div
                                            key={number}
                                            className="grid gap-5 py-7 sm:grid-cols-[64px_1fr_1fr] sm:items-start"
                                        >
                                            <span className="font-mono text-xs text-[#ee2b24]">{number}</span>
                                            <h3 className="text-lg font-bold uppercase">{title}</h3>
                                            <p className="text-sm leading-6 text-white/40">{copy}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="px-5 pb-6 sm:px-8 lg:px-12">
                        <div className="relative mx-auto max-w-[1384px] overflow-hidden border border-white/10 bg-[#101316] px-6 py-20 text-center sm:px-12 lg:py-28">
                            <div className="motologiq-grid absolute inset-0 opacity-20" />
                            <div className="relative">
                                <p className="eyebrow">Your next drive starts here</p>
                                <h2 className="mx-auto mt-6 max-w-5xl text-5xl font-black uppercase leading-[0.88] tracking-[-0.06em] sm:text-8xl">
                                    Never get caught
                                    <br />
                                    <span className="text-[#ee2b24]">off guard.</span>
                                </h2>
                                <Link
                                    href="/register"
                                    className="group mt-10 inline-flex items-center gap-6 bg-white px-7 py-4 text-sm font-black uppercase tracking-[0.12em] text-black transition hover:bg-[#ee2b24] hover:text-white"
                                >
                                    Start your garage <Arrow />
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-[1480px] flex-col gap-10 px-5 py-12 sm:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-12">
                    <div>
                        <BrandLogo wordmarkClassName="text-white" />
                        <p className="mt-5 max-w-sm text-sm leading-6 text-white/30">
                            Precision maintenance intelligence for the cars that move you.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-7 text-xs font-bold uppercase tracking-[0.12em] text-white/35">
                        <a href="#platform">Platform</a>
                        <Link href="/login">Sign in</Link>
                        <Link href="/register">Join Motologiq</Link>
                        <span>© {new Date().getFullYear()}</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
