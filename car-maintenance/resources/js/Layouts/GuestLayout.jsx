import { Head, Link } from '@inertiajs/react';
import BrandLogo from '@/Components/BrandLogo';
import ThemeToggle from '@/Components/ThemeToggle';

export default function GuestLayout({ title, children }) {
    return (
        <div className="editorial-shell relative min-h-screen overflow-hidden bg-[var(--background)] text-[var(--text)] selection:bg-[var(--accent)] selection:text-white">
            <Head title={title} />
            <div className="motologiq-grid pointer-events-none absolute inset-0 opacity-30" />
            <div className="pointer-events-none absolute -right-36 -top-36 h-[32rem] w-[32rem] rounded-full bg-[#ee2b24]/10 blur-[120px]" />

            <header className="relative z-10 mx-auto flex h-20 max-w-[1480px] items-center justify-between px-5 sm:px-8 lg:px-12">
                <Link href="/" aria-label="Motologiq home">
                    <BrandLogo wordmarkClassName="text-[var(--text)]" />
                </Link>
                <ThemeToggle />
            </header>

            <main className="relative z-10 mx-auto grid min-h-[calc(100vh-5rem)] max-w-[1480px] lg:grid-cols-[minmax(0,0.9fr)_minmax(34rem,0.72fr)]">
                <section className="hidden border-r border-[var(--border)] px-12 py-16 lg:flex lg:flex-col lg:justify-between">
                    <div className="max-w-xl pt-[8vh]">
                        <div className="flex items-center gap-3 text-[11px] font-black uppercase tracking-[0.26em] text-white/45">
                            <span className="h-px w-10 bg-[#ee2b24]" /> Your digital garage
                        </div>
                        <p className="mt-8 text-[clamp(4.25rem,6vw,7.5rem)] font-black uppercase leading-[0.8] tracking-[-0.07em]">
                            Stay
                            <br />
                            drive
                            <br />
                            <span className="text-outline">ready.</span>
                        </p>
                        <p className="mt-9 max-w-md text-lg leading-8 text-[var(--text-muted)]">
                            Maintenance history, smart reminders, and the right oil recommendation—ready whenever your
                            car needs it.
                        </p>
                    </div>

                    <div className="grid max-w-xl grid-cols-3 gap-px bg-white/10">
                        {['Track', 'Predict', 'Protect'].map((item, index) => (
                            <div key={item} className="bg-[#090b0d] py-5 pr-4">
                                <span className="font-mono text-[10px] text-[#ee2b24]">/0{index + 1}</span>
                                <p className="mt-2 text-xs font-black uppercase tracking-[0.16em] text-white/55">
                                    {item}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12 lg:py-16">
                    <div className="w-full max-w-[35rem] border border-[var(--border)] bg-[var(--surface)]/95 p-6 shadow-2xl shadow-black/10 backdrop-blur-sm sm:p-9 lg:p-11 dark:shadow-black/40">
                        {children}
                    </div>
                </section>
            </main>
        </div>
    );
}
