import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

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

const growthMetrics = {
    leads: { label: 'Leads', color: '#2563eb', type: 'area' },
    psychologists_registered: { label: 'Nuevos registros', color: '#7c3aed', type: 'line' },
    psychologists_active: { label: 'Nuevos activos', color: '#10b981', type: 'line' },
    profile_views: { label: 'Vistas de perfil', color: '#f59e0b', type: 'line' },
    contact_clicks: { label: 'Clicks de contacto', color: '#ec4899', type: 'line' },
};

export default function Analytics({ auth, analytics, filters }) {
    const [form, setForm] = useState({
        from: filters?.from || '',
        to: filters?.to || '',
        only_activity: filters?.only_activity ?? true,
        lead_status: filters?.lead_status || '',
        granularity: filters?.granularity || 'day',
        search: '',
    });
    const [visibleMetrics, setVisibleMetrics] = useState(['leads', 'psychologists_registered', 'psychologists_active']);
    const [chartMode, setChartMode] = useState('period');

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
            lead_status: form.lead_status || undefined,
            granularity: form.granularity,
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

                        <form onSubmit={submitFilters} className="grid gap-4 border-b border-slate-100 p-5 md:grid-cols-2 xl:grid-cols-[1fr_1fr_180px_auto_auto] xl:items-end">
                            <Field label="Desde">
                                <input
                                    type="date"
                                    value={form.from}
                                    onChange={(event) => setForm((current) => ({ ...current, from: event.target.value }))}
                                    className="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </Field>
                            <Field label="Agrupar por">
                                <select
                                    value={form.granularity}
                                    onChange={(event) => setForm((current) => ({ ...current, granularity: event.target.value }))}
                                    className="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="day">Día</option>
                                    <option value="week">Semana</option>
                                    <option value="month">Mes</option>
                                </select>
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
                        {form.lead_status === 'active' && (
                            <div className="border-t border-slate-100 px-5 pb-5">
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                                    <span className="font-semibold">Mostrando métricas de leads activos: nuevos, vistos, contactados o creados.</span>
                                    <Link href={route('analytics')} className="font-bold hover:underline">Ver todos los leads</Link>
                                </div>
                            </div>
                        )}
                    </section>

                    <section className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <GrowthKpi
                                title="Leads del periodo"
                                value={analytics?.growth?.period?.leads}
                                change={analytics?.growth?.changes?.leads}
                                detail="vs. periodo anterior"
                                tone="blue"
                            />
                            <GrowthKpi
                                title="Psicólogos registrados"
                                value={analytics?.growth?.totals?.registered}
                                change={analytics?.growth?.changes?.psychologists_registered}
                                detail={`+${number(analytics?.growth?.period?.psychologists_registered)} en el periodo`}
                                tone="violet"
                            />
                            <GrowthKpi
                                title="Psicólogos activos"
                                value={analytics?.growth?.totals?.active}
                                change={analytics?.growth?.changes?.psychologists_active}
                                detail={`${Number(analytics?.growth?.totals?.activation_rate || 0).toFixed(1)}% de activación`}
                                tone="emerald"
                            />
                            <GrowthKpi
                                title="Perfiles visibles"
                                value={analytics?.growth?.totals?.visible}
                                detail={`${Number(analytics?.growth?.totals?.visibility_rate || 0).toFixed(1)}% del total registrado`}
                                tone="amber"
                            />
                        </div>

                        <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div>
                                    <p className="text-xs font-black uppercase tracking-[0.2em] text-blue-700">Crecimiento de la plataforma</p>
                                    <h2 className="mt-1 text-xl font-black text-slate-950">Evolución de adquisición y oferta</h2>
                                    <p className="mt-1 text-sm text-slate-500">Activa o desactiva series para comparar las métricas que te interesan.</p>
                                </div>
                                <div className="inline-flex w-fit rounded-xl bg-slate-100 p-1 text-xs font-bold">
                                    <button type="button" onClick={() => setChartMode('period')} className={`rounded-lg px-3 py-2 transition ${chartMode === 'period' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'}`}>Movimiento</button>
                                    <button type="button" onClick={() => setChartMode('total')} className={`rounded-lg px-3 py-2 transition ${chartMode === 'total' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'}`}>Acumulado</button>
                                </div>
                            </div>

                            {chartMode === 'period' && (
                                <div className="mt-5 flex flex-wrap gap-2">
                                    {Object.entries(growthMetrics).map(([key, metric]) => {
                                        const active = visibleMetrics.includes(key);
                                        return (
                                            <button
                                                key={key}
                                                type="button"
                                                onClick={() => setVisibleMetrics((current) => active ? current.filter((item) => item !== key) : [...current, key])}
                                                className={`flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition ${active ? 'border-slate-300 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'}`}
                                            >
                                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: metric.color }} />
                                                {metric.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="mt-6 h-[360px] w-full">
                                <GrowthChart
                                    data={analytics?.growth?.series || []}
                                    visibleMetrics={visibleMetrics}
                                    mode={chartMode}
                                />
                            </div>
                            <p className="mt-3 text-xs text-slate-400">
                                “Activos” refleja el estado actual de las cuentas registradas en cada cohorte; no reconstruye cambios históricos de activación.
                            </p>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                            <LeadStatusCard items={analytics?.growth?.lead_statuses || []} total={analytics?.growth?.period?.leads || 0} />
                            <FunnelCard
                                views={analytics?.summary?.profile_views || 0}
                                contacts={analytics?.summary?.contact_clicks || 0}
                                leads={analytics?.summary?.leads || 0}
                            />
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

function GrowthKpi({ title, value, change, detail, tone }) {
    const tones = {
        blue: 'from-blue-600 to-sky-500',
        violet: 'from-violet-600 to-fuchsia-500',
        emerald: 'from-emerald-600 to-teal-500',
        amber: 'from-amber-500 to-orange-500',
    };
    const hasComparableChange = change !== null && change !== undefined;
    const isPositive = Number(change) >= 0;

    return (
        <article className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className={`absolute inset-x-0 top-0 h-1 bg-gradient-to-r ${tones[tone] || tones.blue}`} />
            <p className="text-xs font-black uppercase tracking-[0.13em] text-slate-500">{title}</p>
            <div className="mt-3 flex items-end justify-between gap-3">
                <p className="text-3xl font-black tracking-tight text-slate-950">{number(value)}</p>
                {change !== undefined && (
                    <span className={`rounded-full px-2.5 py-1 text-xs font-black ${!hasComparableChange ? 'bg-slate-100 text-slate-500' : isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                        {!hasComparableChange ? 'Nuevo' : `${isPositive ? '↑' : '↓'} ${Math.abs(Number(change)).toFixed(1)}%`}
                    </span>
                )}
            </div>
            <p className="mt-2 text-xs font-medium text-slate-500">{detail}</p>
        </article>
    );
}

function GrowthChart({ data, visibleMetrics, mode }) {
    if (!data.length) {
        return <div className="flex h-full items-center justify-center rounded-2xl bg-slate-50 text-sm font-semibold text-slate-500">No hay datos para graficar en este periodo.</div>;
    }

    const tooltipFormatter = (value, key) => [number(value), growthMetrics[key]?.label || (key === 'registered_total' ? 'Registrados acumulados' : 'Activos acumulados')];

    return (
        <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data} margin={{ top: 10, right: 8, left: -12, bottom: 0 }}>
                <defs>
                    <linearGradient id="leadsGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#2563eb" stopOpacity={0.28} />
                        <stop offset="95%" stopColor="#2563eb" stopOpacity={0.02} />
                    </linearGradient>
                    <linearGradient id="registeredGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#7c3aed" stopOpacity={0.24} />
                        <stop offset="95%" stopColor="#7c3aed" stopOpacity={0.02} />
                    </linearGradient>
                </defs>
                <CartesianGrid stroke="#e2e8f0" strokeDasharray="4 6" vertical={false} />
                <XAxis dataKey="label" axisLine={false} tickLine={false} tick={{ fill: '#64748b', fontSize: 11 }} minTickGap={24} />
                <YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: '#64748b', fontSize: 11 }} />
                <Tooltip formatter={tooltipFormatter} contentStyle={{ borderRadius: 16, borderColor: '#e2e8f0', boxShadow: '0 16px 40px rgba(15, 23, 42, .12)' }} />
                <Legend wrapperStyle={{ fontSize: 12, paddingTop: 14 }} />
                {mode === 'total' ? (
                    <>
                        <Area type="monotone" dataKey="registered_total" name="Registrados acumulados" stroke="#7c3aed" fill="url(#registeredGradient)" strokeWidth={3} />
                        <Line type="monotone" dataKey="active_total" name="Activos acumulados" stroke="#10b981" strokeWidth={3} dot={false} activeDot={{ r: 5 }} />
                    </>
                ) : Object.entries(growthMetrics).map(([key, metric]) => visibleMetrics.includes(key) && (
                    metric.type === 'area'
                        ? <Area key={key} type="monotone" dataKey={key} name={metric.label} stroke={metric.color} fill="url(#leadsGradient)" strokeWidth={3} />
                        : <Line key={key} type="monotone" dataKey={key} name={metric.label} stroke={metric.color} strokeWidth={2.5} dot={false} activeDot={{ r: 5 }} />
                ))}
            </AreaChart>
        </ResponsiveContainer>
    );
}

function LeadStatusCard({ items, total }) {
    const labels = { new: 'Nuevos', viewed: 'Vistos', contacted: 'Contactados', created: 'Creados', converted: 'Convertidos', closed: 'Cerrados', sin_estado: 'Sin estado' };
    const palette = ['bg-blue-600', 'bg-sky-500', 'bg-violet-500', 'bg-amber-500', 'bg-emerald-500', 'bg-slate-500'];

    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-black uppercase tracking-[0.2em] text-blue-700">Calidad del pipeline</p>
            <h3 className="mt-1 text-lg font-black text-slate-950">Estado de los leads</h3>
            <div className="mt-5 flex h-3 overflow-hidden rounded-full bg-slate-100">
                {items.map((item, index) => <div key={item.status} className={palette[index % palette.length]} style={{ width: `${total ? (item.total / total) * 100 : 0}%` }} />)}
            </div>
            <div className="mt-5 grid grid-cols-2 gap-3">
                {items.length ? items.map((item, index) => (
                    <div key={item.status} className="flex items-center gap-2 text-sm">
                        <span className={`h-2.5 w-2.5 rounded-full ${palette[index % palette.length]}`} />
                        <span className="min-w-0 flex-1 truncate font-semibold text-slate-600">{labels[item.status] || item.status}</span>
                        <span className="font-black text-slate-950">{number(item.total)}</span>
                    </div>
                )) : <p className="col-span-2 text-sm text-slate-500">Sin leads en este periodo.</p>}
            </div>
        </div>
    );
}

function FunnelCard({ views, contacts, leads }) {
    const rows = [
        { label: 'Vistas únicas', value: views, color: 'bg-blue-600', width: 100 },
        { label: 'Clicks de contacto', value: contacts, color: 'bg-violet-500', width: views ? Math.max((contacts / views) * 100, 12) : 12 },
        { label: 'Leads capturados', value: leads, color: 'bg-emerald-500', width: views ? Math.max((leads / views) * 100, 12) : 12 },
    ];

    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-black uppercase tracking-[0.2em] text-blue-700">Conversión</p>
            <h3 className="mt-1 text-lg font-black text-slate-950">Embudo de adquisición</h3>
            <div className="mt-5 space-y-4">
                {rows.map((row) => (
                    <div key={row.label}>
                        <div className="mb-1.5 flex items-center justify-between text-sm">
                            <span className="font-semibold text-slate-600">{row.label}</span>
                            <span className="font-black text-slate-950">{number(row.value)}</span>
                        </div>
                        <div className="h-8 overflow-hidden rounded-lg bg-slate-100">
                            <div className={`flex h-full items-center rounded-lg px-3 text-xs font-black text-white transition-all ${row.color}`} style={{ width: `${Math.min(row.width, 100)}%` }}>
                                {views && row.label !== 'Vistas únicas' ? `${((row.value / views) * 100).toFixed(1)}%` : ''}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
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
