import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import VendedorLayout from '@/Layouts/VendedorLayout';

function formatMoney(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
    }).format(value ?? 0);
}

const MILESTONE_LABELS = {
    activation: 'Activación',
    month_2: '2 meses activo',
    month_6: '6 meses activo',
    recovery_subscription: 'Suscripción recuperada',
};

const PIPELINE_LABELS = {
    assigned: 'Asignado',
    contacted: 'Contactado',
    follow_up: 'Seguimiento',
    onboarding: 'En onboarding',
    awaiting_payment: 'Esperando pago',
    recovered: 'Recuperado',
    no_response: 'Sin respuesta',
    not_interested: 'No interesado',
};

const STATUS_REFERRAL = {
    active: { label: 'Activo', cls: 'bg-green-100 text-green-700' },
    trial: { label: 'Trial', cls: 'bg-blue-100 text-blue-700' },
    inactive: { label: 'Inactivo', cls: 'bg-gray-100 text-gray-600' },
};

const STATUS_STRIPE = {
    active: { label: 'Activa', cls: 'bg-green-100 text-green-700' },
    trialing: { label: 'Trial', cls: 'bg-blue-100 text-blue-700' },
    unpaid: { label: 'Sin pago', cls: 'bg-red-100 text-red-700' },
    canceled: { label: 'Cancelada', cls: 'bg-gray-100 text-gray-600' },
    past_due: { label: 'Vencida', cls: 'bg-yellow-100 text-yellow-700' },
};

const STATUS_COMMISSION = {
    pending: { label: 'Pendiente', cls: 'bg-yellow-100 text-yellow-700' },
    paid: { label: 'Pagado', cls: 'bg-green-100 text-green-700' },
};

function Badge({ status, map }) {
    const config = map[status] ?? { label: status ?? '—', cls: 'bg-gray-100 text-gray-500' };
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.cls}`}>
            {config.label}
        </span>
    );
}

function ContactPhone({ phone }) {
    if (!phone) {
        return <span className="text-xs text-slate-400">Sin teléfono</span>;
    }

    const phoneValue = String(phone);

    return (
        <a
            href={`tel:${phoneValue.replace(/[^\d+]/g, '')}`}
            className="mt-1 inline-flex text-xs font-semibold text-teal-700 transition hover:text-teal-900 hover:underline"
        >
            {phoneValue}
        </a>
    );
}

function MetricCard({ title, value, subtitle, accent }) {
    return (
        <div className={`bg-white rounded-xl shadow-sm border border-gray-200 p-5 ${accent ?? ''}`}>
            <p className="text-sm text-gray-500 font-medium">{title}</p>
            <p className="mt-1 text-2xl font-bold text-gray-900">{value}</p>
            {subtitle && <p className="mt-1 text-xs text-gray-400">{subtitle}</p>}
        </div>
    );
}

function SectionTitle({ children }) {
    return <h2 className="text-base font-semibold text-gray-800 mb-4">{children}</h2>;
}

function QrCard({ vendedor }) {
    const [copied, setCopied] = useState(false);

    const copyLink = () => {
        navigator.clipboard.writeText(vendedor.registration_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col items-center gap-4">
            <h2 className="text-base font-semibold text-gray-800 self-start">Tu código QR</h2>

            <div className="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <img
                    src={vendedor.qr_preview_url}
                    alt="QR de registro"
                    className="w-44 h-44 object-contain"
                />
            </div>

            <div className="w-full">
                <p className="text-xs text-gray-500 mb-1 font-medium">Enlace de registro</p>
                <div className="flex items-center gap-2">
                    <input
                        readOnly
                        value={vendedor.registration_url}
                        className="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-gray-600 truncate focus:outline-none"
                    />
                    <button
                        onClick={copyLink}
                        className={`shrink-0 text-xs px-3 py-2 rounded-md border transition-colors duration-150 font-medium ${copied
                                ? 'bg-green-50 border-green-300 text-green-700'
                                : 'bg-white border-sky-200 text-sky-700 hover:bg-sky-50'
                            }`}
                    >
                        {copied ? '¡Copiado!' : 'Copiar'}
                    </button>
                </div>
            </div>

            <a
                href={vendedor.qr_download_url}
                download
                className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-teal-500 to-sky-700 hover:from-teal-600 hover:to-sky-800 text-white text-sm font-bold px-4 py-2.5 rounded-lg transition-colors duration-150"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                </svg>
                Descargar QR
            </a>
        </div>
    );
}

function RecoveryDesk({ vendedor, referrals, metrics }) {
    const [selected, setSelected] = useState(null);
    const manual = useForm({ email: '', note: '' });
    const followUp = useForm({ pipeline_status: 'contacted', contact_channel: 'whatsapp', note: '', next_follow_up_at: '' });
    const opportunities = referrals.filter((referral) => ['recovery', 'manual'].includes(referral.source));

    if (!['recovery', 'hybrid'].includes(vendedor.sales_mode)) return null;

    const submitManual = (event) => {
        event.preventDefault();
        manual.post(route('vendedor.recovery.manual.store'), { preserveScroll: true, onSuccess: () => manual.reset() });
    };

    const openFollowUp = (opportunity) => {
        setSelected(opportunity);
        followUp.setData({
            pipeline_status: opportunity.pipeline_status === 'assigned' ? 'contacted' : opportunity.pipeline_status,
            contact_channel: opportunity.last_contact_channel || 'whatsapp',
            note: opportunity.contact_note || '',
            next_follow_up_at: opportunity.next_follow_up_at || '',
        });
    };

    const submitFollowUp = (event) => {
        event.preventDefault();
        if (!selected) return;
        router.patch(route('vendedor.recovery.follow-up.update', selected.id), followUp.data, {
            preserveScroll: true,
            onSuccess: () => setSelected(null),
        });
    };

    return (
        <section className="mb-8 space-y-5">
            <div className="rounded-2xl border border-teal-100 bg-gradient-to-r from-teal-50 via-white to-sky-50 p-6 shadow-sm">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div><p className="text-xs font-bold uppercase tracking-[0.28em] text-teal-700">Cartera de recuperación</p><h2 className="mt-1 text-2xl font-black text-slate-950">Convierte registros pendientes en suscripciones</h2><p className="mt-2 max-w-2xl text-sm text-slate-600">Registra cada seguimiento. Las suscripciones confirmadas este mes acumulan una comisión de {formatMoney(metrics.recovery_commission_rate)} por psicólogo.</p></div>
                    <div className="rounded-xl bg-white px-4 py-3 text-sm font-bold text-teal-800 ring-1 ring-teal-100">{metrics.recovered_this_month} recuperados este mes</div>
                </div>
            </div>

            {vendedor.can_register_manual_sales ? <form onSubmit={submitManual} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex flex-col gap-4 lg:flex-row lg:items-end"><div className="flex-1"><p className="text-sm font-bold text-slate-900">Agregar venta manual</p><p className="mt-1 text-xs text-slate-500">Busca una cuenta de psicólogo ya registrada. No se puede tomar una cartera protegida por otro vendedor.</p></div><input type="email" required value={manual.data.email} onChange={(event) => manual.setData('email', event.target.value)} placeholder="correo@psicologo.com" className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-teal-500"/><input value={manual.data.note} onChange={(event) => manual.setData('note', event.target.value)} placeholder="Nota de origen (opcional)" className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-teal-500"/><button disabled={manual.processing} className="rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-800 disabled:opacity-50">Agregar a mi cartera</button></div>{manual.errors.email ? <p className="mt-2 text-xs font-semibold text-red-600">{manual.errors.email}</p> : null}</form> : null}

            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-100 p-5"><SectionTitle>Mis oportunidades de recuperación</SectionTitle></div>{!opportunities.length ? <p className="p-6 text-sm text-slate-500">Aún no tienes psicólogos asignados en esta cartera.</p> : <table className="min-w-full divide-y divide-slate-100 text-sm"><thead className="bg-slate-50 text-left"><tr><th className="px-4 py-3 font-semibold text-slate-600">Psicólogo</th><th className="px-4 py-3 font-semibold text-slate-600">Origen</th><th className="px-4 py-3 font-semibold text-slate-600">Etapa</th><th className="px-4 py-3 font-semibold text-slate-600">Próximo seguimiento</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100">{opportunities.map((referral) => <tr key={referral.id}><td className="px-4 py-3"><p className="font-semibold text-slate-900">{referral.psychologist?.name}</p><p className="text-xs text-slate-500">{referral.psychologist?.email}</p><ContactPhone phone={referral.psychologist?.phone} /></td><td className="px-4 py-3 text-slate-600">{referral.source === 'manual' ? 'Venta manual' : 'Recuperación'}</td><td className="px-4 py-3"><Badge status={referral.pipeline_status} map={{ ...STATUS_REFERRAL, ...Object.fromEntries(Object.entries(PIPELINE_LABELS).map(([key, label]) => [key, { label, cls: key === 'recovered' ? 'bg-green-100 text-green-700' : 'bg-teal-50 text-teal-700' }])) }} /></td><td className="px-4 py-3 text-slate-600">{referral.next_follow_up_at ? referral.next_follow_up_at.replace('T', ' ') : 'Sin fecha'}</td><td className="px-4 py-3 text-right"><button type="button" onClick={() => openFollowUp(referral)} className="rounded-md border border-teal-200 px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-50">Registrar seguimiento</button></td></tr>)}</tbody></table>}</div>

            {selected ? <form onSubmit={submitFollowUp} className="rounded-xl border border-sky-100 bg-white p-5 shadow-sm"><div className="flex items-center justify-between gap-4"><div><p className="text-sm font-black text-slate-950">Seguimiento: {selected.psychologist?.name}</p><p className="text-xs text-slate-500">Cada contacto queda registrado para proteger tu cartera.</p></div><button type="button" onClick={() => setSelected(null)} className="text-sm font-semibold text-slate-500 hover:text-slate-800">Cerrar</button></div><div className="mt-4 grid gap-3 md:grid-cols-2"><select value={followUp.data.pipeline_status} onChange={(event) => followUp.setData('pipeline_status', event.target.value)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="contacted">Contactado</option><option value="follow_up">Seguimiento</option><option value="onboarding">En onboarding</option><option value="awaiting_payment">Esperando pago</option><option value="no_response">Sin respuesta</option><option value="not_interested">No interesado</option></select><select value={followUp.data.contact_channel} onChange={(event) => followUp.setData('contact_channel', event.target.value)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="whatsapp">WhatsApp</option><option value="call">Llamada</option><option value="email">Correo</option><option value="other">Otro</option></select><input type="datetime-local" value={followUp.data.next_follow_up_at} onChange={(event) => followUp.setData('next_follow_up_at', event.target.value)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm"/><input value={followUp.data.note} onChange={(event) => followUp.setData('note', event.target.value)} placeholder="Nota del contacto" className="rounded-lg border border-slate-200 px-3 py-2 text-sm"/></div><div className="mt-4 flex justify-end"><button disabled={followUp.processing} className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50">Guardar seguimiento</button></div>{Object.values(followUp.errors).map((error) => <p key={error} className="mt-2 text-xs font-semibold text-red-600">{error}</p>)}</form> : null}
        </section>
    );
}

export default function VendedorDashboard({ vendedor, metrics, referrals, commission_items }) {
    return (
        <VendedorLayout vendedor={vendedor}>
            <Head title="Mi Dashboard" />

            <section className="mb-8 rounded-2xl border border-sky-100 bg-gradient-to-r from-sky-50 via-white to-teal-50 p-6 shadow-sm">
                <p className="text-xs font-bold uppercase tracking-[0.3em] text-sky-700">Vendedores MindMeet</p>
                <div className="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-black text-slate-950">Hola, {vendedor.nombre}</h1>
                        <p className="mt-2 max-w-2xl text-sm text-slate-600">
                            Comparte tu QR o enlace de registro. Cada psicologo que active su membresia suma a tus comisiones.
                        </p>
                    </div>
                    <div className="rounded-full bg-white px-4 py-2 text-sm font-bold text-sky-800 ring-1 ring-sky-100">
                        {metrics.active_count} referidos activos
                    </div>
                </div>
            </section>

            {/* QR + metricas */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <div className="lg:col-span-1">
                    <QrCard vendedor={vendedor} />
                </div>
                <div className="lg:col-span-3 flex flex-col gap-4">
                    <SectionTitle>Resumen financiero</SectionTitle>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <MetricCard
                            title="Saldo pendiente"
                            value={formatMoney(metrics.pending_balance)}
                            subtitle="Por cobrar"
                            accent="border-l-4 border-l-sky-600"
                        />
                        <MetricCard
                            title="Total cobrado"
                            value={formatMoney(metrics.paid_total)}
                            subtitle="Historial de pagos"
                            accent="border-l-4 border-l-green-500"
                        />
                        <MetricCard
                            title="Proyección próximo mes"
                            value={formatMoney(metrics.next_projection)}
                            subtitle={`${metrics.active_count} activos × $20`}
                            accent="border-l-4 border-l-blue-500"
                        />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <MetricCard title="Total referidos" value={metrics.referrals_count} />
                        <MetricCard title="Activos" value={metrics.active_count} accent="border-l-4 border-l-green-400" />
                        <MetricCard title="Sin pago / Trial" value={metrics.unpaid_count} accent="border-l-4 border-l-yellow-400" />
                    </div>
                </div>
            </div>

            <RecoveryDesk vendedor={vendedor} referrals={referrals} metrics={metrics} />

            {/* Tabla de referidos */}
            <section className="mb-8">
                <SectionTitle>Psicólogos referidos</SectionTitle>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                    {referrals.length === 0 ? (
                        <p className="p-6 text-sm text-gray-500">Aún no tienes psicólogos registrados con tu código.</p>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Nombre</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Correo</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Teléfono</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Registro</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Fin de trial</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Estado referido</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Suscripción</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Activo</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {referrals.map((ref) => (
                                    <tr key={ref.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 font-medium text-gray-800">{ref.psychologist?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{ref.psychologist?.email ?? '—'}</td>
                                        <td className="px-4 py-3"><ContactPhone phone={ref.psychologist?.phone} /></td>
                                        <td className="px-4 py-3 text-gray-500">{ref.registered_at ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-500">{ref.trial_ends_at ?? '—'}</td>
                                        <td className="px-4 py-3"><Badge status={ref.status} map={STATUS_REFERRAL} /></td>
                                        <td className="px-4 py-3"><Badge status={ref.psychologist?.subscription_status} map={STATUS_STRIPE} /></td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block w-2.5 h-2.5 rounded-full ${ref.psychologist?.activo ? 'bg-green-500' : 'bg-gray-300'}`} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </section>

            {/* Historial de comisiones */}
            <section>
                <SectionTitle>Historial de comisiones</SectionTitle>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                    {commission_items.length === 0 ? (
                        <p className="p-6 text-sm text-gray-500">Todavía no hay comisiones generadas.</p>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Concepto</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Monto</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Estado</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Elegible desde</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Fecha de corte</th>
                                    <th className="px-4 py-3 font-semibold text-gray-600">Pagado el</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {commission_items.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 font-medium text-gray-800">{MILESTONE_LABELS[item.milestone] ?? item.milestone}</td>
                                        <td className="px-4 py-3 font-semibold text-gray-800">{formatMoney(item.amount)}</td>
                                        <td className="px-4 py-3"><Badge status={item.status} map={STATUS_COMMISSION} /></td>
                                        <td className="px-4 py-3 text-gray-500">{item.eligible_at ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-500">{item.cut_date ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-500">{item.paid_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </section>
        </VendedorLayout>
    );
}
