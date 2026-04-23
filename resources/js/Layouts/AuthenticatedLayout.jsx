import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link } from '@inertiajs/react';

const navigationGroups = [
    {
        title: 'General',
        items: [
            { label: 'Dashboard', href: 'dashboard', match: ['dashboard'] },
            { label: 'Analytics', href: 'analytics', match: ['analytics'] },
            { label: 'Centro de ayuda', href: 'help-center.index', match: ['help-center.*'] },
        ],
    },
    {
        title: 'Operacion',
        items: [
            { label: 'Psicologos', href: 'psicologos', match: ['psicologos', 'psicologoShow', 'psicologo.*', 'psicologos.*'] },
            { label: 'Pacientes', href: 'pacientes', match: ['pacientes', 'paciente', 'pacientes.*', 'paciente.*'] },
            { label: 'Carritos', href: 'carts', match: ['carts', 'cartByPatient'] },
        ],
    },
    {
        title: 'Growth',
        items: [
            { label: 'Catalogo Facebook', href: 'facebook-catalog.index', match: ['facebook-catalog.*'] },
            { label: 'Cupones', href: 'coupons', match: ['coupons', 'coupons.*'] },
            { label: 'Vendedores', href: 'vendedores', match: ['vendedores', 'vendedores.*'] },
            { label: 'Pagos vendedores', href: 'seller-commissions', match: ['seller-commissions', 'seller-commissions.*'] },
        ],
    },
    {
        title: 'Comunidad',
        items: [
            { label: 'Comunidad Minder', href: 'minder.groups.index', match: ['minder.*'] },
        ],
    },
];

export default function Authenticated({ user, header, children }) {
    const [showSidebar, setShowSidebar] = useState(false);

    return (
        <div className="min-h-screen bg-slate-100">
            <div className="flex min-h-screen">
                <aside className="hidden w-72 shrink-0 border-r border-slate-200 bg-slate-950 text-slate-100 lg:flex lg:flex-col">
                    <SidebarContent user={user} onNavigate={() => { }} />
                </aside>

                {showSidebar ? (
                    <div className="fixed inset-0 z-40 bg-slate-950/45 lg:hidden" onClick={() => setShowSidebar(false)}>
                        <aside
                            className="h-full w-80 max-w-[86vw] bg-slate-950 text-slate-100 shadow-2xl"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <SidebarContent user={user} onNavigate={() => setShowSidebar(false)} />
                        </aside>
                    </div>
                ) : null}

                <div className="min-w-0 flex-1">
                    <nav className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                        <div className="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setShowSidebar(true)}
                                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 lg:hidden"
                                >
                                    <svg className="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M4 7h16M4 12h16M4 17h16" />
                                    </svg>
                                </button>

                                <div className="lg:hidden">
                                    <Link href="/" className="flex items-center gap-2">
                                        <ApplicationLogo className="block h-9 w-auto fill-current text-slate-900" />
                                    </Link>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="hidden text-right sm:block">
                                    <p className="text-sm font-semibold text-slate-900">{user.name}</p>
                                    <p className="text-xs text-slate-500">{user.email}</p>
                                </div>

                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-xl">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900 focus:outline-none"
                                            >
                                                Cuenta
                                                <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit.su')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </nav>

                    {header ? (
                        <header className="border-b border-slate-200 bg-white">
                            <div className="px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                        </header>
                    ) : null}

                    <main>{children}</main>
                </div>
            </div>
        </div>
    );
}

function SidebarContent({ user, onNavigate }) {
    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-white/10 px-5 py-5">
                <Link href="/" className="flex items-center gap-3" onClick={onNavigate}>
                    <ApplicationLogo className="block h-10 w-auto fill-current text-white" />
                    <div>
                        <p className="text-sm font-bold uppercase tracking-[0.24em] text-blue-200">MindMeet</p>
                        <p className="text-xs text-slate-400">Admin central</p>
                    </div>
                </Link>
            </div>

            <div className="flex-1 space-y-6 overflow-y-auto px-4 py-6">
                {navigationGroups.map((group) => (
                    <div key={group.title}>
                        <p className="px-3 text-[11px] font-bold uppercase tracking-[0.24em] text-slate-500">
                            {group.title}
                        </p>
                        <div className="mt-2 space-y-1">
                            {group.items.map((item) => (
                                <SidebarLink key={item.label} item={item} onNavigate={onNavigate} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <div className="border-t border-white/10 px-5 py-4">
                <p className="text-sm font-semibold text-white">{user.name}</p>
                <p className="mt-1 text-xs text-slate-400">{user.email}</p>
            </div>
        </div>
    );
}

function SidebarLink({ item, onNavigate }) {
    const active = item.match.some((pattern) => route().current(pattern));

    return (
        <Link
            href={route(item.href)}
            onClick={onNavigate}
            className={`flex items-center justify-between rounded-2xl px-3 py-3 text-sm font-medium transition ${
                active
                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white'
            }`}
        >
            <span>{item.label}</span>
            {active ? <span className="h-2 w-2 rounded-full bg-white" /> : null}
        </Link>
    );
}
