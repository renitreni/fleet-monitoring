import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';

const statusConfig = {
    ok: { label: 'Ready', classes: 'border-green-500/25 bg-green-500/10 text-green-700 dark:text-green-300' },
    due_soon: { label: 'Due soon', classes: 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300' },
    overdue: { label: 'Overdue', classes: 'border-red-500/25 bg-red-500/10 text-red-700 dark:text-red-300' },
};

const buttonClasses =
    'inline-flex items-center justify-center bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-2 focus:ring-offset-[var(--background)]';

function StatCard({ index, label, value, tone = 'default' }) {
    const tones = {
        default: 'text-[var(--text)]',
        danger: 'text-red-600 dark:text-red-400',
        warning: 'text-amber-600 dark:text-amber-400',
    };
    return (
        <div className="border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-7">
            <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.2em] text-[var(--text-muted)]">
                <span>/{index}</span>
                <span>{label}</span>
            </div>
            <p className={`mt-8 font-mono text-6xl font-bold tracking-[-0.08em] ${tones[tone]}`}>
                {String(value).padStart(2, '0')}
            </p>
        </div>
    );
}

export default function Dashboard() {
    const { auth, stats, cars } = usePage().props;
    return (
        <AuthenticatedLayout
            title="Dashboard"
            header={
                <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[var(--accent)]">
                        Garage / Overview
                    </p>
                    <h1 className="mt-2 text-3xl font-black uppercase tracking-[-0.04em]">Dashboard</h1>
                </div>
            }
        >
            <div className="motologiq-grid pointer-events-none fixed inset-0 opacity-25" />
            <div className="relative mx-auto max-w-[1480px] px-5 py-8 sm:px-8 lg:px-12 lg:py-12">
                <section className="grid border border-[var(--border)] bg-[var(--surface)] lg:grid-cols-[1.25fr_.75fr]">
                    <div className="p-7 sm:p-10 lg:p-12">
                        <p className="eyebrow">Command center</p>
                        <h2 className="mt-7 max-w-3xl text-5xl font-black uppercase leading-[0.88] tracking-[-0.055em] sm:text-7xl">
                            Welcome back,
                            <br />
                            <span className="text-[var(--text-muted)]">{auth.user?.name}.</span>
                        </h2>
                        <p className="mt-7 max-w-xl text-base leading-7 text-[var(--text-muted)]">
                            Your garage status, maintenance timing, and next actions—clear before the next drive.
                        </p>
                    </div>
                    <div className="flex flex-col justify-between border-t border-[var(--border)] bg-[var(--surface-muted)] p-7 lg:border-l lg:border-t-0 lg:p-10">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[var(--text-muted)]">
                                Fleet status
                            </p>
                            <p className="mt-4 flex items-center gap-3 text-sm font-black uppercase tracking-[0.14em]">
                                <span className="h-2 w-2 rounded-full bg-green-500" />
                                Monitoring active
                            </p>
                        </div>
                        <Link href="/cars/create" className={`${buttonClasses} mt-10 w-fit`}>
                            Add a car <span className="ml-5">→</span>
                        </Link>
                    </div>
                </section>

                <section className="mt-6 grid gap-4 sm:grid-cols-3">
                    <StatCard index="01" label="Total cars" value={stats.total_cars} />
                    <StatCard index="02" label="Overdue" value={stats.overdue} tone="danger" />
                    <StatCard index="03" label="Due soon" value={stats.due_soon} tone="warning" />
                </section>

                <section className="mt-12">
                    <div className="mb-5 flex items-end justify-between gap-5">
                        <div>
                            <p className="eyebrow">Your garage</p>
                            <h2 className="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">Vehicle roster</h2>
                        </div>
                        <Link
                            href="/cars"
                            className="hidden text-xs font-black uppercase tracking-[0.16em] text-[var(--text-muted)] hover:text-[var(--accent)] sm:block"
                        >
                            View all →
                        </Link>
                    </div>
                    {cars.length === 0 ? (
                        <EmptyState
                            icon="🚗"
                            title="No cars yet"
                            description="Add your first car to start tracking maintenance and oil changes."
                            action={
                                <Link href="/cars/create" className={buttonClasses}>
                                    Add your first car
                                </Link>
                            }
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {cars.map((car, index) => {
                                const config = statusConfig[car.oil_status ?? 'unknown'] ?? {
                                    label: 'No data',
                                    classes:
                                        'border-[var(--border)] bg-[var(--surface-muted)] text-[var(--text-muted)]',
                                };
                                return (
                                    <Link
                                        key={car.id}
                                        href={`/cars/${car.id}`}
                                        className="group border border-[var(--border)] bg-[var(--surface)] p-6 transition hover:-translate-y-1 hover:border-[var(--accent)] hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <span className="font-mono text-[10px] text-[var(--accent)]">
                                                /{String(index + 1).padStart(2, '0')}
                                            </span>
                                            <span
                                                className={`border px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] ${config.classes}`}
                                            >
                                                {config.label}
                                            </span>
                                        </div>
                                        <h3 className="mt-10 text-2xl font-bold tracking-[-0.035em] group-hover:text-[var(--accent)]">
                                            {car.year} {car.make}
                                            <br />
                                            {car.model}
                                        </h3>
                                        <dl className="mt-8 grid grid-cols-2 gap-px bg-[var(--border)]">
                                            <div className="bg-[var(--surface)] py-3 pr-3">
                                                <dt className="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--text-muted)]">
                                                    Mileage
                                                </dt>
                                                <dd className="mt-1 font-mono text-sm font-bold">
                                                    {car.current_mileage.toLocaleString()} km
                                                </dd>
                                            </div>
                                            <div className="bg-[var(--surface)] py-3 pl-3">
                                                <dt className="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--text-muted)]">
                                                    Country
                                                </dt>
                                                <dd className="mt-1 font-mono text-sm font-bold">{car.country}</dd>
                                            </div>
                                        </dl>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
