import { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import BrandLogo from '@/Components/BrandLogo';
import FlashMessage from '@/Components/FlashMessage';
import NotificationBell from '@/Components/NotificationBell';
import ThemeToggle from '@/Components/ThemeToggle';

const navigation = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'My cars', href: '/cars' },
];

export default function AuthenticatedLayout({ title, header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const url = page.url;
    const { post } = useForm();
    const [menuOpen, setMenuOpen] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (menuRef.current && !menuRef.current.contains(event.target)) setMenuOpen(false);
        };
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, []);

    const handleLogout = (event) => {
        event.preventDefault();
        post('/logout');
    };
    const initials = user?.name
        ? user.name
              .split(' ')
              .map((part) => part[0])
              .filter(Boolean)
              .slice(0, 2)
              .join('')
              .toUpperCase()
        : '?';
    const isActive = (href) => url === href || (href === '/cars' && url.startsWith('/cars'));

    return (
        <div className="app-shell min-h-screen bg-[var(--background)] text-[var(--text)] selection:bg-[var(--accent)] selection:text-white">
            <Head title={title} />
            <nav className="sticky top-0 z-40 border-b border-[var(--border)] bg-[var(--background)]/95 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-[1480px] items-center justify-between px-5 sm:px-8 lg:px-12">
                    <div className="flex items-center gap-10">
                        <Link href="/" aria-label="Motologiq home">
                            <BrandLogo wordmarkClassName="text-[var(--text)]" />
                        </Link>
                        <div className="hidden items-center gap-8 md:flex">
                            {navigation.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`border-b-2 py-7 text-[11px] font-black uppercase tracking-[0.18em] transition ${isActive(item.href) ? 'border-[var(--accent)] text-[var(--text)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text)]'}`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div className="flex items-center gap-2 sm:gap-3">
                        <ThemeToggle />
                        <NotificationBell />
                        <div className="relative" ref={menuRef}>
                            <button
                                type="button"
                                onClick={() => setMenuOpen((open) => !open)}
                                aria-expanded={menuOpen}
                                className="flex h-10 items-center gap-3 border border-[var(--border)] bg-[var(--surface)] px-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                            >
                                <span className="grid h-7 w-7 place-items-center bg-[var(--accent)] text-[10px] font-black text-white">
                                    {initials}
                                </span>
                                <span className="hidden max-w-36 truncate font-bold sm:block">{user?.name}</span>
                                <span className="text-[var(--text-muted)]" aria-hidden="true">
                                    ⌄
                                </span>
                            </button>
                            {menuOpen && (
                                <div className="absolute right-0 top-full mt-2 w-60 border border-[var(--border)] bg-[var(--surface)] shadow-2xl">
                                    <div className="border-b border-[var(--border)] px-4 py-4">
                                        <p className="truncate text-sm font-bold">{user?.name}</p>
                                        <p className="mt-1 truncate text-xs text-[var(--text-muted)]">{user?.email}</p>
                                    </div>
                                    <div className="p-2">
                                        {navigation.map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={() => setMenuOpen(false)}
                                                className="block px-3 py-2 text-sm font-semibold hover:bg-[var(--surface-muted)]"
                                            >
                                                {item.label}
                                            </Link>
                                        ))}
                                        <form onSubmit={handleLogout}>
                                            <button
                                                type="submit"
                                                className="block w-full px-3 py-2 text-left text-sm font-semibold text-[var(--accent)] hover:bg-[var(--surface-muted)]"
                                            >
                                                Log out
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={() => setMobileOpen((open) => !open)}
                            aria-label="Toggle navigation"
                            aria-expanded={mobileOpen}
                            className="grid h-10 w-10 place-items-center border border-[var(--border)] md:hidden"
                        >
                            {mobileOpen ? '×' : '☰'}
                        </button>
                    </div>
                </div>
                {mobileOpen && (
                    <div className="border-t border-[var(--border)] bg-[var(--surface)] px-5 py-3 md:hidden">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => setMobileOpen(false)}
                                className={`block border-l-2 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] ${isActive(item.href) ? 'border-[var(--accent)] text-[var(--text)]' : 'border-transparent text-[var(--text-muted)]'}`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                )}
            </nav>
            {header && (
                <header className="border-b border-[var(--border)] bg-[var(--surface)]">
                    <div className="mx-auto max-w-[1480px] px-5 py-7 sm:px-8 lg:px-12">{header}</div>
                </header>
            )}
            <main>
                <FlashMessage />
                {children}
            </main>
        </div>
    );
}
