import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

const filterLabels = {
    all: 'Todos',
    public_visible: 'Públicos',
    active: 'Aprobados',
    identity_review: 'Por revisar',
    rejected: 'Rechazados',
    incomplete_profiles: 'Perfiles incompletos',
    without_subscription: 'Sin suscripción',
};

const identityMap = {
    approved: { label: 'Aprobado', className: 'bg-emerald-50 text-emerald-700 ring-emerald-100' },
    sending: { label: 'Recibido', className: 'bg-amber-50 text-amber-700 ring-amber-100' },
    pending: { label: 'Pendiente', className: 'bg-slate-100 text-slate-700 ring-slate-200' },
    rejected: { label: 'Rechazado', className: 'bg-rose-50 text-rose-700 ring-rose-100' },
};

const subscriptionMap = {
    active: { label: 'Activa', className: 'bg-emerald-50 text-emerald-700 ring-emerald-100' },
    trialing: { label: 'Prueba', className: 'bg-amber-50 text-amber-700 ring-amber-100' },
    trial: { label: 'Prueba', className: 'bg-amber-50 text-amber-700 ring-amber-100' },
    canceled: { label: 'Cancelada', className: 'bg-rose-50 text-rose-700 ring-rose-100' },
    past_due: { label: 'Vencida', className: 'bg-red-50 text-red-700 ring-red-100' },
    trial_expired: { label: 'Prueba expirada', className: 'bg-orange-50 text-orange-700 ring-orange-100' },
    lifetime: { label: 'Permanente', className: 'bg-sky-50 text-sky-700 ring-sky-100' },
    content_creator: { label: 'Creador de contenido', className: 'bg-violet-50 text-violet-700 ring-violet-100' },
    none: { label: 'Sin suscripción', className: 'bg-slate-100 text-slate-600 ring-slate-200' },
};

const tableStyles = {
    headCells: {
        style: {
            color: '#475569',
            fontSize: '11px',
            fontWeight: 800,
            letterSpacing: '0.08em',
            textTransform: 'uppercase',
        },
    },
    rows: {
        style: {
            minHeight: '76px',
        },
    },
};

export default function Psicologos({ auth, psicologos = [], summary = {}, filters = {} }) {
    const [search, setSearch] = useState('');
    const currentFilter = filters?.filter || 'all';

    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();
        const sorted = [...psicologos].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

        if (!term) {
            return sorted;
        }

        return sorted.filter((item) => [
            item.name,
            item.email,
            item.contacto?.whatsapp,
            item.contacto?.telefono,
            item.address?.estado,
            identityMap[item.identity_verification_status]?.label,
            getSubscriptionMeta(item).label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(term));
    }, [psicologos, search]);

    const columns = [
        {
            name: 'Profesional',
            grow: 2.2,
            selector: row => row?.name || '',
            sortable: true,
            cell: row => (
                <div className="flex items-center gap-3 py-3">
                    <FotoPerfil image={row?.image || null} name={row?.name} className="h-12 w-12 rounded-full" alt={row?.name} />
                    <div className="min-w-0">
                        <Link href={route('psicologoShow', row.id)} className="font-black text-slate-950 hover:text-sky-700">
                            {row?.name || 'Sin nombre'}
                        </Link>
                        <p className="mt-0.5 max-w-[260px] truncate text-xs text-slate-500">{row?.email || 'Sin correo'}</p>
                    </div>
                </div>
            ),
        },
        {
            name: 'Validación',
            selector: row => row?.identity_verification_status || '',
            sortable: true,
            cell: row => {
                const meta = identityMap[row?.identity_verification_status] || identityMap.pending;

                return <Badge meta={meta} />;
            },
        },
        {
            name: 'Perfil',
            selector: row => Boolean(row?.isProfileComplete),
            sortable: true,
            cell: row => (
                <Badge
                    meta={row?.isProfileComplete
                        ? { label: 'Completo', className: 'bg-emerald-50 text-emerald-700 ring-emerald-100' }
                        : { label: 'Incompleto', className: 'bg-amber-50 text-amber-700 ring-amber-100' }}
                />
            ),
        },
        {
            name: 'Suscripción',
            selector: row => getSubscriptionMeta(row).label,
            sortable: true,
            cell: row => <Badge meta={getSubscriptionMeta(row)} />,
        },
        {
            name: 'Contacto',
            grow: 1.5,
            selector: row => row?.contacto?.whatsapp || row?.contacto?.telefono || '',
            cell: row => {
                const phone = row?.contacto?.whatsapp || row?.contacto?.telefono;

                return (
                    <div className="text-sm">
                        {phone ? (
                            <Link href={`https://wa.me/${cleanPhone(phone)}`} target="_blank" className="font-semibold text-sky-700 hover:underline">
                                {phone}
                            </Link>
                        ) : (
                            <span className="text-slate-400">Sin teléfono</span>
                        )}
                        <p className="mt-1 text-xs text-slate-500">{row?.address?.estado || 'Sin estado'}</p>
                    </div>
                );
            },
        },
        {
            name: 'Acciones',
            right: true,
            cell: row => (
                <Link
                    href={route('psicologoShow', row.id)}
                    className="rounded-lg border border-sky-200 px-3 py-2 text-xs font-black uppercase text-sky-700 transition hover:bg-sky-50"
                >
                    Revisar
                </Link>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-xs font-black uppercase tracking-[0.28em] text-sky-700">Operación</p>
                    <h1 className="mt-1 text-2xl font-black text-slate-950">Psicólogos</h1>
                    <p className="mt-1 text-sm text-slate-500">Gestiona validación, suscripción y visibilidad pública.</p>
                </div>
            }
        >
            <Head title="Psicólogos" />

            <main className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <Kpi label="Total" value={summary.total || 0} />
                        <Kpi label="Públicos" value={summary.public_visible || 0} tone="green" />
                        <Kpi label="Por revisar" value={summary.identity_review || 0} tone="amber" />
                        <Kpi label="Aprobados" value={summary.active || 0} tone="sky" />
                        <Kpi label="Rechazados" value={summary.rejected || 0} tone="rose" />
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-5">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Centro de revisión</p>
                                    <h2 className="mt-1 text-xl font-black text-slate-950">
                                        {filterLabels[currentFilter] || 'Psicólogos'}
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {rows.length} de {summary.total || psicologos.length} profesionales en esta vista.
                                    </p>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input
                                        type="search"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Buscar nombre, correo, teléfono o estado"
                                        className="w-full rounded-xl border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:w-80"
                                    />
                                    {currentFilter !== 'all' && (
                                        <Link href={route('psicologos')} className="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                                            Ver todos
                                        </Link>
                                    )}
                                </div>
                            </div>

                            <div className="mt-5 flex flex-wrap gap-2">
                                {[
                                    ['all', summary.total],
                                    ['public_visible', summary.public_visible],
                                    ['identity_review', summary.identity_review],
                                    ['active', summary.active],
                                    ['rejected', summary.rejected],
                                    ['incomplete_profiles', summary.incomplete_profiles],
                                    ['without_subscription', summary.without_subscription],
                                ].map(([key, count]) => (
                                    <Link
                                        key={key}
                                        href={route('psicologos', key === 'all' ? {} : { filter: key })}
                                        className={`rounded-full px-4 py-2 text-sm font-black transition ${
                                            currentFilter === key
                                                ? 'bg-sky-700 text-white shadow-sm'
                                                : 'border border-slate-200 text-slate-600 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700'
                                        }`}
                                    >
                                        {filterLabels[key]} <span className="ml-1 opacity-80">{count || 0}</span>
                                    </Link>
                                ))}
                            </div>

                            {filters?.focus === 'manual_cedulas' && (
                                <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                                    Vienes desde cédulas manuales pendientes. Esta vista muestra psicólogos por revisar; falta una pantalla específica para resolver esas cédulas manuales una por una.
                                </div>
                            )}
                        </div>

                        <DataTable
                            columns={columns}
                            data={rows}
                            customStyles={tableStyles}
                            pagination
                            paginationPerPage={10}
                            persistTableHead
                            responsive
                            noDataComponent="No hay psicólogos para estos filtros."
                        />
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function Kpi({ label, value, tone = 'slate' }) {
    const tones = {
        slate: 'border-slate-200 bg-white text-slate-950',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        green: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        rose: 'border-rose-200 bg-rose-50 text-rose-800',
        sky: 'border-sky-200 bg-sky-50 text-sky-800',
    };

    return (
        <div className={`rounded-2xl border p-5 shadow-sm ${tones[tone]}`}>
            <p className="text-xs font-black uppercase tracking-[0.14em] opacity-70">{label}</p>
            <p className="mt-3 text-3xl font-black">{Number(value || 0).toLocaleString('es-MX')}</p>
        </div>
    );
}

function Badge({ meta }) {
    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-black ring-1 ${meta.className}`}>
            {meta.label}
        </span>
    );
}

function getSubscriptionMeta(row) {
    if (row?.has_lifetime_access) {
        return row?.membership_type === 'content_creator'
            ? subscriptionMap.content_creator
            : subscriptionMap.lifetime;
    }

    return subscriptionMap[row?.subscription?.stripe_status] || subscriptionMap.none;
}

function cleanPhone(phone) {
    return String(phone || '').replace(/[()\-\s+]/g, '');
}
