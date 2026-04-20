import { useState } from 'react';
import { Head } from '@inertiajs/react';
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
                                : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                            }`}
                    >
                        {copied ? '¡Copiado!' : 'Copiar'}
                    </button>
                </div>
            </div>

            <a
                href={vendedor.qr_download_url}
                download
                className="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors duration-150"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                </svg>
                Descargar QR
            </a>
        </div>
    );
}

export default function VendedorDashboard({ vendedor, metrics, referrals, commission_items }) {
    return (
        <VendedorLayout vendedor={vendedor}>
            <Head title="Mi Dashboard" />

            {/* QR + métricas */}
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
                            accent="border-l-4 border-l-indigo-500"
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
