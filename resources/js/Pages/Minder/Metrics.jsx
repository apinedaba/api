import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';

function MetricCard({ label, value, color = 'blue' }) {
    const colors = {
        blue: 'bg-blue-50 border-blue-200 text-blue-700',
        green: 'bg-green-50 border-green-200 text-green-700',
        purple: 'bg-purple-50 border-purple-200 text-purple-700',
    };
    return (
        <div className={`rounded-xl border p-5 ${colors[color]}`}>
            <p className="text-xs font-semibold uppercase tracking-wide opacity-70">{label}</p>
            <p className="text-3xl font-bold mt-1">{value?.toLocaleString('es-MX')}</p>
        </div>
    );
}

export default function MinderMetrics({ auth, messages_per_day, active_groups, totals, days }) {
    const [selectedDays, setSelectedDays] = useState(days);

    const applyFilter = (d) => {
        setSelectedDays(d);
        router.get(route('minder.metrics.index'), { days: d }, { preserveState: true });
    };

    const maxMessages = Math.max(...messages_per_day.map(d => d.total), 1);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('minder.groups.index')} className="text-sm text-blue-600 hover:underline">← Grupos</Link>
                    <span className="text-gray-400">/</span>
                    <h2 className="font-semibold text-xl text-gray-800">Métricas Comunidad Minder</h2>
                </div>
            }
        >
            <Head title="Métricas Minder" />
            <div className="py-8">
                <div className="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* KPIs */}
                    <div className="grid grid-cols-3 gap-4">
                        <MetricCard label="Grupos activos" value={totals.groups} color="blue" />
                        <MetricCard label="Mensajes (período)" value={totals.messages} color="green" />
                        <MetricCard label="Membresías totales" value={totals.members} color="purple" />
                    </div>

                    {/* Filtro de días */}
                    <div className="flex gap-2">
                        {[7, 14, 30, 60, 90].map(d => (
                            <button key={d} onClick={() => applyFilter(d)}
                                className={`px-3 py-1 text-sm rounded-full border transition ${selectedDays === d ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300 hover:border-blue-400'}`}>
                                {d} días
                            </button>
                        ))}
                    </div>

                    {/* Gráfica de barras (CSS nativo) */}
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        <h3 className="font-semibold text-gray-800 mb-4">Mensajes por día</h3>
                        {messages_per_day.length === 0
                            ? <p className="text-sm text-slate-400">Sin datos para este período.</p>
                            : (
                                <div className="flex items-end gap-1 h-40 overflow-x-auto">
                                    {messages_per_day.map(day => (
                                        <div key={day.date} className="flex flex-col items-center gap-1 min-w-[28px]">
                                            <div
                                                title={`${day.date}: ${day.total} mensajes`}
                                                className="bg-blue-500 rounded-t w-6 hover:bg-blue-600 transition"
                                                style={{ height: `${Math.round((day.total / maxMessages) * 128)}px` }}
                                            />
                                            <span className="text-[9px] text-slate-400 rotate-45 origin-left w-12 truncate">{day.date.slice(5)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                    </div>

                    {/* Grupos más activos */}
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        <h3 className="font-semibold text-gray-800 mb-4">Grupos más activos</h3>
                        {active_groups.length === 0
                            ? <p className="text-sm text-slate-400">Sin datos.</p>
                            : (
                                <div className="space-y-3">
                                    {active_groups.map((g, i) => (
                                        <div key={g.id} className="flex items-center gap-3">
                                            <span className="text-sm font-bold text-slate-400 w-5">{i + 1}</span>
                                            <Link href={route('minder.groups.show', g.id)}
                                                className="flex-1 text-sm font-semibold text-blue-700 hover:underline truncate">
                                                {g.name}
                                            </Link>
                                            <span className="text-sm text-slate-600">{g.messages_count} msg</span>
                                            <div className="w-32 bg-slate-100 rounded-full h-2">
                                                <div className="bg-blue-500 h-2 rounded-full"
                                                    style={{ width: `${Math.round((g.messages_count / (active_groups[0]?.messages_count || 1)) * 100)}%` }} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
