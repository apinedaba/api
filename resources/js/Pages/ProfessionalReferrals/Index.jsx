import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const statusLabels = {
    registered: 'Registrado',
    trialing: 'En prueba',
    qualified: 'Calificado',
    inactive: 'Inactivo',
    cancelled: 'Cancelado',
    pending: 'Pendiente',
    approved: 'Aprobada',
    applied: 'Aplicada',
};

const statusClasses = {
    registered: 'bg-sky-50 text-sky-700 border-sky-200',
    trialing: 'bg-amber-50 text-amber-700 border-amber-200',
    qualified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    inactive: 'bg-slate-100 text-slate-600 border-slate-200',
    cancelled: 'bg-rose-50 text-rose-700 border-rose-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-sky-50 text-sky-700 border-sky-200',
    applied: 'bg-emerald-50 text-emerald-700 border-emerald-200',
};

export default function Index({ auth, referrals, rules = [], rewards = [], filters = {}, stats = {} }) {
    const ruleForm = useForm({
        name: '',
        required_qualified_referrals: '',
        reward_months: '',
        description: '',
        is_active: true,
    });

    const applySearch = (event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(route('professional-referrals.index'), {
            search: data.get('search') || '',
            status: data.get('status') || '',
        }, { preserveState: true, replace: true });
    };

    const updateReward = (reward, status) => {
        router.patch(route('professional-referrals.rewards.update', reward.id), { status }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-xs font-black uppercase tracking-[0.28em] text-sky-700">Growth</p>
                    <h1 className="mt-1 text-2xl font-black text-slate-950">Referidos de psicólogos</h1>
                    <p className="mt-1 text-sm text-slate-500">Códigos, referidos calificados y recompensas configurables.</p>
                </div>
            }
        >
            <Head title="Referidos de psicólogos" />

            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <section className="grid gap-4 md:grid-cols-4">
                        <Stat label="Registrados" value={stats.registered || 0} />
                        <Stat label="En prueba" value={stats.trialing || 0} />
                        <Stat label="Calificados" value={stats.qualified || 0} />
                        <Stat label="Recompensas pendientes" value={stats.pending_rewards || 0} />
                    </section>

                    <section className="grid gap-6 xl:grid-cols-[1.5fr_0.8fr]">
                        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Seguimiento</p>
                                        <h2 className="mt-1 text-xl font-black text-slate-950">Referidos registrados</h2>
                                    </div>
                                    <form onSubmit={applySearch} className="flex flex-wrap gap-2">
                                        <input
                                            name="search"
                                            defaultValue={filters.search || ''}
                                            className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-400"
                                            placeholder="Buscar psicólogo o correo"
                                        />
                                        <select
                                            name="status"
                                            defaultValue={filters.status || ''}
                                            className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-400"
                                        >
                                            <option value="">Todos</option>
                                            <option value="registered">Registrados</option>
                                            <option value="trialing">En prueba</option>
                                            <option value="qualified">Calificados</option>
                                            <option value="inactive">Inactivos</option>
                                        </select>
                                        <button className="rounded-xl bg-sky-700 px-4 py-2 text-sm font-black text-white transition hover:bg-sky-800">
                                            Filtrar
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                                        <tr>
                                            <th className="px-5 py-3">Referente</th>
                                            <th className="px-5 py-3">Referido</th>
                                            <th className="px-5 py-3">Código</th>
                                            <th className="px-5 py-3">Estado</th>
                                            <th className="px-5 py-3">Fechas</th>
                                            <th className="px-5 py-3 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {referrals.data.map((referral) => (
                                            <tr key={referral.id} className="align-top">
                                                <td className="px-5 py-4">
                                                    <p className="font-black text-slate-950">{referral.referrer?.name || 'Sin nombre'}</p>
                                                    <p className="text-xs text-slate-500">{referral.referrer?.email}</p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p className="font-black text-slate-950">{referral.referred?.name || 'Sin nombre'}</p>
                                                    <p className="text-xs text-slate-500">{referral.referred?.email}</p>
                                                </td>
                                                <td className="px-5 py-4 font-mono text-xs text-slate-600">{referral.code || '-'}</td>
                                                <td className="px-5 py-4"><Badge status={referral.status} /></td>
                                                <td className="px-5 py-4 text-xs text-slate-500">
                                                    <p>Registro: {dateLabel(referral.registered_at)}</p>
                                                    <p>Pago: {dateLabel(referral.first_paid_at)}</p>
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(route('professional-referrals.sync', referral.id), {}, { preserveScroll: true })}
                                                        className="rounded-xl border border-sky-200 px-3 py-2 text-xs font-black text-sky-700 transition hover:bg-sky-50"
                                                    >
                                                        Validar membresía
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex items-center justify-between border-t border-slate-100 p-5 text-sm text-slate-500">
                                <span>{referrals.from || 0}-{referrals.to || 0} de {referrals.total || 0}</span>
                                <div className="flex gap-2">
                                    {referrals.prev_page_url && <Link href={referrals.prev_page_url} className="font-bold text-sky-700">Anterior</Link>}
                                    {referrals.next_page_url && <Link href={referrals.next_page_url} className="font-bold text-sky-700">Siguiente</Link>}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-6">
                            <Panel eyebrow="Configurable" title="Reglas de recompensa">
                                <div className="space-y-3">
                                    {rules.map((rule) => (
                                        <RuleEditor key={rule.id} rule={rule} />
                                    ))}
                                </div>

                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        ruleForm.post(route('professional-referrals.rules.store'), {
                                            preserveScroll: true,
                                            onSuccess: () => ruleForm.reset(),
                                        });
                                    }}
                                    className="mt-5 space-y-3 rounded-xl bg-slate-50 p-4"
                                >
                                    <p className="font-black text-slate-950">Nueva regla</p>
                                    <input value={ruleForm.data.name} onChange={(event) => ruleForm.setData('name', event.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Ej. 3 referidos = 1 mes" />
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <input value={ruleForm.data.required_qualified_referrals} onChange={(event) => ruleForm.setData('required_qualified_referrals', event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Referidos" />
                                        <input value={ruleForm.data.reward_months} onChange={(event) => ruleForm.setData('reward_months', event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Meses gratis" />
                                    </div>
                                    <button className="w-full rounded-xl bg-sky-700 px-4 py-2 text-sm font-black text-white transition hover:bg-sky-800">
                                        Crear regla
                                    </button>
                                </form>
                            </Panel>

                            <Panel eyebrow="Pendientes" title="Recompensas recientes">
                                <div className="space-y-3">
                                    {rewards.map((reward) => (
                                        <div key={reward.id} className="rounded-xl border border-slate-200 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-black text-slate-950">{reward.referrer?.name || 'Sin nombre'}</p>
                                                    <p className="text-sm text-slate-500">{reward.reward_months} mes(es) gratis</p>
                                                </div>
                                                <Badge status={reward.status} />
                                            </div>
                                            <div className="mt-3 flex gap-2">
                                                <button onClick={() => updateReward(reward, 'approved')} className="rounded-lg border border-sky-200 px-3 py-1 text-xs font-bold text-sky-700">Aprobar</button>
                                                <button onClick={() => updateReward(reward, 'applied')} className="rounded-lg border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700">Aplicada</button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Panel>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-black uppercase tracking-[0.14em] text-slate-500">{label}</p>
            <p className="mt-3 text-3xl font-black text-slate-950">{value}</p>
        </div>
    );
}

function Panel({ eyebrow, title, children }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">{eyebrow}</p>
            <h2 className="mt-1 text-xl font-black text-slate-950">{title}</h2>
            <div className="mt-5">{children}</div>
        </div>
    );
}

function Badge({ status, label }) {
    return (
        <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-black ${statusClasses[status] || statusClasses.registered}`}>
            {label || statusLabels[status] || status}
        </span>
    );
}

function RuleEditor({ rule }) {
    const form = useForm({
        name: rule.name || '',
        required_qualified_referrals: rule.required_qualified_referrals || '',
        reward_months: rule.reward_months || '',
        description: rule.description || '',
        is_active: Boolean(rule.is_active),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(route('professional-referrals.rules.update', rule.id), { preserveScroll: true });
            }}
            className="rounded-xl border border-slate-200 p-4"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1 space-y-2">
                    <input
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-950"
                    />
                    <div className="grid gap-2 sm:grid-cols-2">
                        <input
                            value={form.data.required_qualified_referrals}
                            onChange={(event) => form.setData('required_qualified_referrals', event.target.value)}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Referidos activos"
                        />
                        <input
                            value={form.data.reward_months}
                            onChange={(event) => form.setData('reward_months', event.target.value)}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Meses gratis"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                            className="rounded border-slate-300 text-sky-700"
                        />
                        Regla activa
                    </label>
                </div>
                <Badge status={form.data.is_active ? 'qualified' : 'inactive'} label={form.data.is_active ? 'Activa' : 'Pausada'} />
            </div>
            <button className="mt-3 rounded-lg border border-sky-200 px-3 py-2 text-xs font-black text-sky-700 transition hover:bg-sky-50">
                Guardar regla
            </button>
        </form>
    );
}

function dateLabel(value) {
    return value ? new Date(value).toLocaleDateString('es-MX') : 'Sin fecha';
}
