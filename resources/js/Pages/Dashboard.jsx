import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

const number = new Intl.NumberFormat('es-MX');

const toneClasses = {
    green: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    blue: 'border-sky-200 bg-sky-50 text-sky-700',
    rose: 'border-rose-200 bg-rose-50 text-rose-700',
};

export default function Dashboard({
    auth,
    summary = {},
    attentionItems = [],
    recentActivity = [],
    todayAppointments = [],
    community = {},
    quickLinks = [],
}) {
    const leadConversion = summary.leads_month > 0
        ? `${Math.round((summary.converted_leads_month / summary.leads_month) * 100)}%`
        : '0%';

    const cards = [
        {
            label: 'Psicólogos visibles',
            value: number.format(summary.psychologists_visible || 0),
            detail: `${number.format(summary.psychologists_total || 0)} registrados`,
        },
        {
            label: 'Pacientes',
            value: number.format(summary.patients_total || 0),
            detail: 'Base total registrada',
        },
        {
            label: 'Citas hoy',
            value: number.format(summary.appointments_today || 0),
            detail: `${number.format(summary.appointments_month || 0)} este mes`,
        },
        {
            label: 'Pagos del mes',
            value: money.format(summary.payments_month || 0),
            detail: `${number.format(summary.active_subscriptions || 0)} suscripciones activas/prueba`,
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-xs font-black uppercase tracking-[0.28em] text-sky-700">MindMeet central</p>
                    <h1 className="mt-1 text-2xl font-black text-slate-950">Dashboard operativo</h1>
                    <p className="mt-1 text-sm text-slate-500">Prioridades, actividad y salud general de la plataforma.</p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {cards.map((card) => (
                            <div key={card.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-black uppercase tracking-[0.14em] text-slate-500">{card.label}</p>
                                <p className="mt-3 text-3xl font-black text-slate-950">{card.value}</p>
                                <p className="mt-2 text-sm text-slate-500">{card.detail}</p>
                            </div>
                        ))}
                    </section>

                    <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Requiere atención</p>
                                        <h2 className="mt-1 text-xl font-black text-slate-950">Pendientes operativos</h2>
                                    </div>
                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                        {number.format(attentionItems.reduce((total, item) => total + Number(item.value || 0), 0))} pendientes
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-3 p-5 md:grid-cols-2">
                                {attentionItems.map((item) => (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className="rounded-xl border border-slate-200 p-4 transition hover:border-sky-200 hover:bg-sky-50/40"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-bold text-slate-900">{item.label}</p>
                                                <p className="mt-1 text-sm text-slate-500">{item.hint}</p>
                                            </div>
                                            <span className={`rounded-full border px-3 py-1 text-sm font-black ${toneClasses[item.tone] || toneClasses.blue}`}>
                                                {number.format(item.value || 0)}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Crecimiento</p>
                            <h2 className="mt-1 text-xl font-black text-slate-950">Pulso del mes</h2>

                            <div className="mt-5 grid gap-3">
                                <MetricRow label="Leads recibidos" value={number.format(summary.leads_month || 0)} />
                                <MetricRow label="Leads convertidos" value={number.format(summary.converted_leads_month || 0)} />
                                <MetricRow label="Conversión" value={leadConversion} />
                                <MetricRow label="Clínicas activas" value={number.format(summary.clinics_active || 0)} />
                                <MetricRow label="Preguntas abiertas" value={number.format(community.open_questions || 0)} />
                            </div>

                            <div className="mt-5 grid gap-2 sm:grid-cols-2">
                                {quickLinks.map((link) => (
                                    <Link
                                        key={link.label}
                                        href={link.href}
                                        className="rounded-xl border border-slate-200 px-3 py-2 text-center text-sm font-bold text-sky-700 transition hover:border-sky-200 hover:bg-sky-50"
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-6 xl:grid-cols-2">
                        <Panel
                            eyebrow="Agenda"
                            title="Citas de hoy"
                            empty="No hay citas programadas para hoy."
                        >
                            {todayAppointments.map((appointment) => (
                                <div key={appointment.id} className="flex items-start justify-between gap-4 border-b border-slate-100 py-4 last:border-0">
                                    <div>
                                        <p className="font-black text-slate-950">{appointment.time} · {appointment.title}</p>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {appointment.patient} con {appointment.psychologist}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                        {appointment.status}
                                    </span>
                                </div>
                            ))}
                        </Panel>

                        <Panel
                            eyebrow="Actividad"
                            title="Movimiento reciente"
                            empty="Aún no hay actividad reciente."
                        >
                            {recentActivity.map((item, index) => (
                                <Link
                                    key={`${item.type}-${index}`}
                                    href={item.href}
                                    className="block border-b border-slate-100 py-4 transition last:border-0 hover:bg-slate-50"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-xs font-black uppercase tracking-[0.14em] text-sky-700">{item.type}</p>
                                            <p className="mt-1 font-black text-slate-950">{item.title}</p>
                                            <p className="mt-1 text-sm text-slate-500">{item.subtitle}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold text-slate-400">{item.date}</span>
                                    </div>
                                </Link>
                            ))}
                        </Panel>
                    </section>

                    <section className="grid gap-4 md:grid-cols-3">
                        <HealthCard label="Reportes pendientes" value={community.pending_reports || 0} href={route('minder.forum-reports.index')} />
                        <HealthCard label="Evaluaciones bajas" value={community.low_feedback || 0} href={route('mindmeet-feedback.index', { max_rating: 3 })} />
                        <HealthCard label="Perfiles incompletos" value={attentionItems.find((item) => item.label === 'Perfiles incompletos')?.value || 0} href={route('psicologos', { filter: 'incomplete_profiles' })} />
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function MetricRow({ label, value }) {
    return (
        <div className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
            <span className="text-sm font-semibold text-slate-600">{label}</span>
            <span className="text-lg font-black text-slate-950">{value}</span>
        </div>
    );
}

function Panel({ eyebrow, title, empty, children }) {
    const hasChildren = Array.isArray(children) ? children.length > 0 : Boolean(children);

    return (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 p-5">
                <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">{eyebrow}</p>
                <h2 className="mt-1 text-xl font-black text-slate-950">{title}</h2>
            </div>
            <div className="p-5">
                {hasChildren ? children : (
                    <p className="rounded-xl bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">{empty}</p>
                )}
            </div>
        </div>
    );
}

function HealthCard({ label, value, href }) {
    const hasIssue = Number(value) > 0;

    return (
        <Link
            href={href}
            className={`rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 ${
                hasIssue
                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800'
            }`}
        >
            <p className="text-sm font-black uppercase tracking-[0.14em]">{label}</p>
            <p className="mt-3 text-3xl font-black">{number.format(value || 0)}</p>
            <p className="mt-1 text-sm font-semibold">{hasIssue ? 'Revisar' : 'Sin pendientes'}</p>
        </Link>
    );
}
