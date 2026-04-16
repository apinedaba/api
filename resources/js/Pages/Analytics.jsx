import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

const number = (value) => new Intl.NumberFormat('es-MX').format(Number(value || 0));
const percent = (value) => `${Number(value || 0).toFixed(2)}%`;

const statusLabels = {
    active: 'Activa',
    trialing: 'Prueba',
    trial: 'Prueba',
    lifetime: 'Permanente',
    canceled: 'Cancelada',
    past_due: 'Vencida',
};

export default function Analytics({ auth, analytics, filters }) {
    const [form, setForm] = useState({
        from: filters?.from || '',
        to: filters?.to || '',
        only_activity: filters?.only_activity ?? true,
        search: '',
    });

    const professionals = analytics?.professionals || [];
    const filteredProfessionals = useMemo(() => {
        const search = form.search.trim().toLowerCase();
        if (!search) return professionals;

        return professionals.filter((item) => (
            item.name?.toLowerCase().includes(search)
            || item.email?.toLowerCase().includes(search)
        ));
    }, [professionals, form.search]);

    const maxViews = Math.max(...professionals.map((item) => item.totals.profile_views), 1);

    const submitFilters = (event) => {
        event.preventDefault();
        router.get(route('analytics'), {
            from: form.from,
            to: form.to,
            only_activity: form.only_activity ? 1 : 0,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns = [
        {
            name: 'Psicologo',
            selector: row => row.name,
            sortable: true,
            grow: 2,
            cell: row => (
                <div className="flex items-center gap-3 py-3">
                    <FotoPerfil image={row.image || null} name={row.name} className="h-10 w-10 rounded-full" alt={row.name} />
                    <div>
                        <Link href={`/psicologo/${row.id}`} className="font-semibold text-slate-900 hover:text-blue-700">
                            {row.name}
                        </Link>
                        <p className="text-xs text-slate-500">{row.email}</p>
                    </div>
                </div>
            ),
        },
        {
            name: 'Vistas',
            selector: row => row.totals.profile_views,
            sortable: true,
            cell: row => (
                <MetricWithBar value={row.totals.profile_views} max={maxViews} />
            ),
        },
        {
            name: 'Contactos',
            selector: row => row.totals.contact_clicks,
            sortable: true,
            cell: row => number(row.totals.contact_clicks),
        },
        {
            name: 'WhatsApp',
            selector: row => row.totals.whatsapp_clicks,
            sortable: true,
            cell: row => number(row.totals.whatsapp_clicks),
        },
        {
            name: 'Leads',
            selector: row => row.totals.leads,
            sortable: true,
            cell: row => number(row.totals.leads),
        },
        {
            name: 'Conversion',
            selector: row => row.rates.lead_conversion,
            sortable: true,
            cell: row => <span className="font-semibold text-emerald-700">{percent(row.rates.lead_conversion)}</span>,
        },
        {
            name: 'Estado',
            selector: row => row.subscription_status || 'sin_suscripcion',
            cell: row => (
                <div className="flex flex-col gap-1 text-xs">
                    <span className={`w-fit rounded-full px-2 py-1 font-semibold ${row.activo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                        {row.activo ? 'Activo' : 'Inactivo'}
                    </span>
                    <span className="text-slate-500">
                        {statusLabels[row.subscription_status] || 'Sin suscripcion'}
                    </span>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Analytics generales</h2>}
        >
            <Head title="Analytics" />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 p-6 text-white">
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">MindMeet intelligence</p>
                            <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h1 className="text-3xl font-black tracking-tight">Interaccion por psicologo</h1>
                                    <p className="mt-2 max-w-3xl text-sm text-blue-100">
                                        Conteo unico por sesion o IP para evitar que recargas inflen las metricas. Ideal para medir catalogo,
                                        campañas, leads y conversion interna.
                                    </p>
                                </div>
                                <span className="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold">
                                    {analytics?.range?.from} a {analytics?.range?.to}
                                </span>
                            </div>
                        </div>

                        <form onSubmit={submitFilters} className="grid gap-4 border-b border-slate-100 p-5 md:grid-cols-[1fr_1fr_auto_auto] md:items-end">
                            <Field label="Desde">
                                <input
                                    type="date"
                                    value={form.from}
                                    onChange={(event) => setForm((current) => ({ ...current, from: event.target.value }))}
                                    className="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </Field>
                            <Field label="Hasta">
                                <input
                                    type="date"
                                    value={form.to}
                                    onChange={(event) => setForm((current) => ({ ...current, to: event.target.value }))}
                                    className="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </Field>
                            <label className="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={form.only_activity}
                                    onChange={() => setForm((current) => ({ ...current, only_activity: !current.only_activity }))}
                                    className="rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                                />
                                Solo con actividad
                            </label>
                            <button className="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800">
                                Aplicar filtros
                            </button>
                        </form>

                        <div className="grid gap-4 p-5 md:grid-cols-5">
                            <Kpi title="Psicologos con actividad" value={analytics?.summary?.professionals_with_activity} />
                            <Kpi title="Vistas unicas" value={analytics?.summary?.profile_views} />
                            <Kpi title="Clicks de contacto" value={analytics?.summary?.contact_clicks} />
                            <Kpi title="Leads capturados" value={analytics?.summary?.leads} />
                            <Kpi title="Conversion global" value={percent(analytics?.summary?.lead_conversion)} />
                        </div>
                    </section>

                    <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
                        <div className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-slate-900">Ranking por psicologo</h2>
                                    <p className="text-sm text-slate-500">Compara vistas, clicks, leads y conversion.</p>
                                </div>
                                <input
                                    type="search"
                                    value={form.search}
                                    onChange={(event) => setForm((current) => ({ ...current, search: event.target.value }))}
                                    placeholder="Buscar psicologo"
                                    className="rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <DataTable
                                columns={columns}
                                data={filteredProfessionals}
                                pagination
                                paginationPerPage={12}
                                persistTableHead
                                responsive
                            />
                        </div>

                        <aside className="space-y-6">
                            <BreakdownCard title="Fuentes con mas interaccion" items={analytics?.topInteractionSources || []} labelKey="source" />
                            <BreakdownCard title="Fuentes con mas leads" items={analytics?.topSources || []} labelKey="source" />
                            <BreakdownCard title="Campañas con mas interaccion" items={analytics?.topCampaigns || []} labelKey="campaign" />
                            <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 className="text-base font-bold text-slate-900">Eventos mapeados</h3>
                                <div className="mt-4 grid gap-2">
                                    {Object.entries(analytics?.eventLabels || {}).map(([key, label]) => (
                                        <div key={key} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs">
                                            <span className="font-mono text-slate-500">{key}</span>
                                            <span className="font-semibold text-slate-700">{label}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </aside>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, children }) {
    return (
        <label className="block text-sm font-semibold text-slate-700">
            <span className="mb-1 block">{label}</span>
            {children}
        </label>
    );
}

function Kpi({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{title}</p>
            <p className="mt-2 text-2xl font-black text-slate-950">{typeof value === 'string' ? value : number(value)}</p>
        </div>
    );
}

function MetricWithBar({ value, max }) {
    const width = Math.max((Number(value || 0) / max) * 100, value ? 8 : 0);

    return (
        <div className="w-full min-w-[120px]">
            <div className="mb-1 font-semibold text-slate-800">{number(value)}</div>
            <div className="h-2 rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-blue-700" style={{ width: `${width}%` }} />
            </div>
        </div>
    );
}

function BreakdownCard({ title, items, labelKey }) {
    const max = Math.max(...items.map((item) => Number(item.total || 0)), 1);

    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="text-base font-bold text-slate-900">{title}</h3>
            <div className="mt-4 space-y-3">
                {items.length ? items.map((item) => (
                    <div key={item[labelKey]} className="space-y-1">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-semibold text-slate-700">{item[labelKey]}</span>
                            <span className="text-slate-500">{number(item.total)}</span>
                        </div>
                        <div className="h-2 rounded-full bg-slate-100">
                            <div
                                className="h-2 rounded-full bg-emerald-500"
                                style={{ width: `${Math.max((Number(item.total || 0) / max) * 100, 8)}%` }}
                            />
                        </div>
                    </div>
                )) : (
                    <p className="text-sm text-slate-500">Aun no hay datos en este rango.</p>
                )}
            </div>
        </div>
    );
}
