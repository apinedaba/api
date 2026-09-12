import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowPathIcon, ClockIcon, UserGroupIcon, UserPlusIcon } from '@heroicons/react/24/outline';
import { useMemo, useState } from 'react';

const SOURCE_LABELS = { recovery: 'Recuperación', manual: 'Venta manual' };
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

function Badge({ children, tone = 'slate' }) {
    const tones = {
        slate: 'bg-slate-100 text-slate-700',
        teal: 'bg-teal-50 text-teal-700',
        amber: 'bg-amber-50 text-amber-700',
        emerald: 'bg-emerald-50 text-emerald-700',
    };

    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${tones[tone]}`}>{children}</span>;
}

function ContactPhone({ phone }) {
    if (!phone) {
        return <span className="text-xs text-slate-400">Sin teléfono</span>;
    }

    const phoneValue = String(phone);

    return <a href={`tel:${phoneValue.replace(/[^\d+]/g, '')}`} className="mt-1 inline-flex text-xs font-semibold text-teal-700 transition hover:text-teal-900 hover:underline">{phoneValue}</a>;
}

function Metric({ label, value, hint, icon: Icon }) {
    return (
        <div className="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{label}</p>
                    <p className="mt-2 text-2xl font-black text-slate-950">{value}</p>
                    <p className="mt-1 text-xs text-slate-500">{hint}</p>
                </div>
                <span className="rounded-xl bg-sky-50 p-2 text-sky-700"><Icon className="h-5 w-5" /></span>
            </div>
        </div>
    );
}

export default function SellerRecovery({ auth, recoverySellers = [], candidates = [], assignments = [], policy = {} }) {
    const { flash, errors = {} } = usePage().props;
    const [search, setSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkSellerId, setBulkSellerId] = useState('');
    const [showBulkConfirmation, setShowBulkConfirmation] = useState(false);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);

    const visibleCandidates = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return candidates;
        return candidates.filter((candidate) => [candidate.name, candidate.email].some((value) => value?.toLowerCase().includes(term)));
    }, [candidates, search]);

    const visibleIds = visibleCandidates.map((candidate) => candidate.id);
    const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedIds.includes(id));
    const selectedCandidates = candidates.filter((candidate) => selectedIds.includes(candidate.id));
    const selectedSeller = recoverySellers.find((seller) => String(seller.id) === String(bulkSellerId));

    const toggleCandidate = (userId) => {
        setSelectedIds((current) => current.includes(userId)
            ? current.filter((id) => id !== userId)
            : [...current, userId]);
        setShowBulkConfirmation(false);
    };

    const toggleVisible = () => {
        setSelectedIds((current) => allVisibleSelected
            ? current.filter((id) => !visibleIds.includes(id))
            : [...new Set([...current, ...visibleIds])]);
        setShowBulkConfirmation(false);
    };

    const assignBulk = () => {
        if (!bulkSellerId || !selectedIds.length || bulkSubmitting) return;
        router.post(route('seller-recovery.assign-bulk'), {
            vendedor_id: bulkSellerId,
            user_ids: selectedIds,
        }, {
            preserveScroll: true,
            onStart: () => setBulkSubmitting(true),
            onSuccess: () => {
                setSelectedIds([]);
                setBulkSellerId('');
                setShowBulkConfirmation(false);
            },
            onFinish: () => setBulkSubmitting(false),
        });
    };

    const activeAssignments = assignments.filter((item) => item.pipeline_status !== 'recovered');
    const recovered = assignments.filter((item) => item.pipeline_status === 'recovered');

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Recuperación comercial</h2>}>
            <Head title="Recuperación de psicólogos" />

            <div className="bg-slate-100 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success ? <div role="status" className="fixed bottom-6 right-6 z-50 max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-xl">{flash.success}</div> : null}
                    {Object.keys(errors).length ? <div role="alert" className="fixed bottom-6 right-6 z-50 max-w-md rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700 shadow-xl">{Object.values(errors)[0]}</div> : null}

                    <section className="overflow-hidden rounded-2xl bg-slate-950 p-6 text-white shadow-sm">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.3em] text-cyan-200">Growth · recuperación</p>
                                <h1 className="mt-2 text-3xl font-black">Vuelve a activar psicólogos con potencial</h1>
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                                    Asigna únicamente cuentas sin suscripción activa. Cada recuperador tiene una cartera protegida para evitar contactos duplicados.
                                </p>
                            </div>
                            <div className="rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-slate-200">
                                Protección de recuperación: <strong>{policy.recovery_window_days || 21} días</strong> · Venta manual: <strong>{policy.manual_window_days || 60} días</strong>
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-4 md:grid-cols-3">
                        <Metric label="Disponibles" value={candidates.length} hint="Registros sin pago elegibles" icon={UserGroupIcon} />
                        <Metric label="En cartera" value={activeAssignments.length} hint="Recuperaciones y ventas manuales activas" icon={ClockIcon} />
                        <Metric label="Recuperados" value={recovered.length} hint="Suscripciones atribuidas" icon={ArrowPathIcon} />
                    </section>

                    <section className="rounded-xl border border-sky-100 bg-white shadow-sm">
                        <div className="flex flex-col gap-4 border-b border-slate-100 p-6 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.28em] text-sky-700">Bolsa disponible</p>
                                <h2 className="mt-1 text-xl font-black text-slate-950">Asignar psicólogos a un recuperador</h2>
                                <p className="mt-1 text-sm text-slate-500">Solo aparecen cuentas con al menos 7 días de registro y sin suscripción activa.</p>
                            </div>
                            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por nombre o correo" className="rounded-lg border border-sky-100 px-3 py-2 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100" />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th className="w-12 px-5 py-3"><input type="checkbox" checked={allVisibleSelected} onChange={toggleVisible} aria-label="Seleccionar psicólogos visibles" className="rounded border-slate-300 text-sky-700 focus:ring-sky-500" /></th><th className="px-5 py-3">Psicólogo</th><th className="px-5 py-3">Registro</th><th className="px-5 py-3">Progreso</th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {visibleCandidates.map((candidate) => (
                                        <tr key={candidate.id} className={`transition ${selectedIds.includes(candidate.id) ? 'bg-sky-50/70' : 'hover:bg-sky-50/40'}`}>
                                            <td className="px-5 py-4"><input type="checkbox" checked={selectedIds.includes(candidate.id)} onChange={() => toggleCandidate(candidate.id)} aria-label={`Seleccionar a ${candidate.name}`} className="rounded border-slate-300 text-sky-700 focus:ring-sky-500" /></td>
                                            <td className="px-5 py-4"><p className="font-bold text-slate-900">{candidate.name}</p><p className="text-xs text-slate-500">{candidate.email}</p><ContactPhone phone={candidate.phone} /></td>
                                            <td className="px-5 py-4 text-slate-600">{candidate.registered_at}</td>
                                            <td className="px-5 py-4"><div className="flex flex-wrap gap-2"><Badge tone={candidate.is_profile_complete ? 'emerald' : 'amber'}>{candidate.is_profile_complete ? 'Perfil completo' : 'Perfil incompleto'}</Badge><Badge>{candidate.subscription_status}</Badge></div></td>
                                        </tr>
                                    ))}
                                    {!visibleCandidates.length ? <tr><td colSpan="4" className="px-5 py-10 text-center text-sm text-slate-500">No hay psicólogos disponibles con estos criterios.</td></tr> : null}
                                </tbody>
                            </table>
                        </div>

                        {selectedIds.length ? <div className="sticky bottom-4 z-20 m-4 rounded-xl border border-sky-200 bg-slate-950 p-4 text-white shadow-xl"><div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"><div className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-500 text-lg font-black">{selectedIds.length}</span><div><p className="font-bold">{selectedIds.length} psicólogo{selectedIds.length === 1 ? '' : 's'} seleccionado{selectedIds.length === 1 ? '' : 's'}</p><p className="text-xs text-slate-300">Selecciona un recuperador y revisa el resumen antes de confirmar.</p></div></div><div className="flex flex-col gap-2 sm:flex-row"><select value={bulkSellerId} onChange={(event) => { setBulkSellerId(event.target.value); setShowBulkConfirmation(false); }} disabled={bulkSubmitting} className="min-w-60 rounded-lg border border-slate-600 bg-white px-3 py-2 text-sm font-semibold text-slate-800 disabled:opacity-60"><option value="">Selecciona recuperador</option>{recoverySellers.map((seller) => <option value={seller.id} key={seller.id}>{seller.nombre} · {seller.sales_mode === 'hybrid' ? 'Mixto' : 'Recuperación'}</option>)}</select><button type="button" onClick={() => setShowBulkConfirmation(true)} disabled={!bulkSellerId || bulkSubmitting} className="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-bold text-white hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-40"><UserPlusIcon className="h-4 w-4" />Revisar asignación</button><button type="button" disabled={bulkSubmitting} onClick={() => { setSelectedIds([]); setShowBulkConfirmation(false); }} className="rounded-lg border border-slate-600 px-3 py-2 text-sm font-bold text-slate-200 hover:bg-white/10 disabled:opacity-40">Limpiar</button></div></div>{showBulkConfirmation ? <div className="mt-4 rounded-lg border border-sky-400/30 bg-white/10 p-4"><p className="font-bold text-sky-100">Confirmar asignación masiva</p><p className="mt-1 text-sm text-slate-200">Asignarás <strong>{selectedIds.length}</strong> psicólogo{selectedIds.length === 1 ? '' : 's'} a <strong>{selectedSeller?.nombre}</strong>. Se protegerán por {policy.recovery_window_days || 21} días.</p><p className="mt-2 text-xs text-slate-300">{selectedCandidates.slice(0, 5).map((candidate) => candidate.name).join(' · ')}{selectedCandidates.length > 5 ? ` y ${selectedCandidates.length - 5} más` : ''}</p><div className="mt-3 flex gap-2"><button type="button" disabled={bulkSubmitting} onClick={assignBulk} className="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-400 disabled:cursor-wait disabled:opacity-60">{bulkSubmitting ? 'Asignando…' : 'Confirmar y asignar'}</button><button type="button" disabled={bulkSubmitting} onClick={() => setShowBulkConfirmation(false)} className="rounded-lg border border-slate-500 px-4 py-2 text-sm font-bold text-slate-100 hover:bg-white/10 disabled:opacity-40">Volver</button></div></div> : null}</div> : null}
                    </section>

                    <section className="rounded-xl border border-slate-100 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-6"><p className="text-xs font-bold uppercase tracking-[0.28em] text-teal-700">Carteras activas</p><h2 className="mt-1 text-xl font-black text-slate-950">Seguimiento y atribución</h2><p className="mt-1 text-sm text-slate-500">La cartera se libera al vencer su protección o se marca recuperada al confirmar el pago.</p></div>
                        <div className="overflow-x-auto"><table className="min-w-full divide-y divide-slate-100 text-sm"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Psicólogo</th><th className="px-5 py-3">Vendedor</th><th className="px-5 py-3">Origen</th><th className="px-5 py-3">Etapa</th><th className="px-5 py-3">Contactos</th><th className="px-5 py-3">Protección</th></tr></thead><tbody className="divide-y divide-slate-100">{assignments.map((item) => <tr key={item.id}><td className="px-5 py-4"><p className="font-bold text-slate-900">{item.psychologist?.name}</p><p className="text-xs text-slate-500">{item.psychologist?.email}</p><ContactPhone phone={item.psychologist?.phone} /></td><td className="px-5 py-4 text-slate-700">{item.seller?.nombre}</td><td className="px-5 py-4"><Badge tone={item.source === 'manual' ? 'teal' : 'slate'}>{SOURCE_LABELS[item.source] || item.source}</Badge></td><td className="px-5 py-4"><Badge tone={item.pipeline_status === 'recovered' ? 'emerald' : 'amber'}>{PIPELINE_LABELS[item.pipeline_status] || item.pipeline_status}</Badge></td><td className="px-5 py-4 text-slate-600">{item.contact_attempts || 0}</td><td className="px-5 py-4 text-slate-600">{item.claimed_until || '—'}</td></tr>)}{!assignments.length ? <tr><td colSpan="6" className="px-5 py-10 text-center text-sm text-slate-500">Todavía no hay oportunidades asignadas.</td></tr> : null}</tbody></table></div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
