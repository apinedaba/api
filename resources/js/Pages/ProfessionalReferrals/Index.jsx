import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

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

const rewardModeLabels = {
    free_months: 'Meses gratis',
    mentepuntos: 'MindPoints',
};

export default function Index({ auth, referrals, rules = [], rewards = [], pointAccounts = [], settings = {}, filters = {}, stats = {} }) {
    const [activePageTab, setActivePageTab] = useState('tracking');
    const [activeConfigTab, setActiveConfigTab] = useState('points');
    const ruleForm = useForm({
        name: '',
        required_qualified_referrals: '',
        reward_months: '',
        description: '',
        is_active: true,
    });
    const settingsForm = useForm({
        points_enabled: Boolean(settings.points_enabled),
        points_per_qualified_referral: settings.points_per_qualified_referral || 10,
        points_name: settings.points_name || 'MindPoints',
        points_description: settings.points_description || '',
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
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <Stat label="Registrados" value={stats.registered || 0} />
                        <Stat label="En prueba" value={stats.trialing || 0} />
                        <Stat label="Calificados" value={stats.qualified || 0} />
                        <Stat label="Recompensas pendientes" value={stats.pending_rewards || 0} />
                        <Stat label="Saldo MindPoints" value={stats.points_balance || 0} />
                    </section>

                    <div className="border-b border-slate-200">
                        <div className="flex flex-wrap gap-6">
                            <PageTabButton active={activePageTab === 'tracking'} onClick={() => setActivePageTab('tracking')}>
                                Seguimiento
                            </PageTabButton>
                            <PageTabButton active={activePageTab === 'settings'} onClick={() => setActivePageTab('settings')}>
                                Configuracion
                            </PageTabButton>
                        </div>
                    </div>

                    {activePageTab === 'tracking' && (
                    <section>
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
                                            <th className="px-5 py-3">Modalidad</th>
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
                                                <td className="px-5 py-4">
                                                    <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-black text-slate-600">
                                                        {rewardModeLabels[referral.reward_mode || 'free_months'] || 'Sin definir'}
                                                    </span>
                                                </td>
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
                    </section>
                    )}

                    {activePageTab === 'settings' && (
                    <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Configuracion</p>
                                <h2 className="mt-1 text-xl font-black text-slate-950">Recompensas</h2>
                                <p className="mt-1 text-sm text-slate-500">Administra las modalidades sin mezclar reglas, saldos y pendientes.</p>
                            </div>

                            <div className="border-b border-slate-100 px-4 pt-4">
                                <div className="grid gap-2 rounded-2xl bg-slate-50 p-1 sm:grid-cols-3">
                                    <TabButton active={activeConfigTab === 'points'} onClick={() => setActiveConfigTab('points')}>
                                        MindPoints
                                    </TabButton>
                                    <TabButton active={activeConfigTab === 'months'} onClick={() => setActiveConfigTab('months')}>
                                        Meses gratis
                                    </TabButton>
                                    <TabButton active={activeConfigTab === 'pending'} onClick={() => setActiveConfigTab('pending')}>
                                        Pendientes
                                    </TabButton>
                                </div>
                            </div>

                            <div className="p-5">
                                {activeConfigTab === 'points' && (
                                    <div>
                                        <div className="rounded-2xl border border-sky-100 bg-sky-50/70 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-black text-slate-950">
                                                        {settingsForm.data.points_enabled ? 'Acumulacion activa' : 'Acumulacion pausada'}
                                                    </p>
                                                    <p className="mt-1 text-sm leading-relaxed text-slate-600">
                                                        Los psicologos podran elegir saldo virtual cuando este modulo este activo. Cada referido calificado suma puntos a su cuenta. 1 MindPoint equivale a $1 MXN.
                                                    </p>
                                                </div>
                                                <Badge
                                                    status={settingsForm.data.points_enabled ? 'qualified' : 'inactive'}
                                                    label={settingsForm.data.points_enabled ? 'Activo' : 'Pausado'}
                                                />
                                            </div>
                                        </div>

                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                settingsForm.patch(route('professional-referrals.settings.update'), { preserveScroll: true });
                                            }}
                                            className="mt-4 space-y-3"
                                        >
                                            <label className="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    checked={settingsForm.data.points_enabled}
                                                    onChange={(event) => settingsForm.setData('points_enabled', event.target.checked)}
                                                    className="rounded border-slate-300 text-sky-700"
                                                />
                                                Activar MindPoints para psicologos
                                            </label>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                <label className="space-y-1 text-xs font-black uppercase tracking-[0.12em] text-slate-500">
                                                    Nombre
                                                    <input
                                                        value={settingsForm.data.points_name}
                                                        onChange={(event) => settingsForm.setData('points_name', event.target.value)}
                                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold normal-case tracking-normal text-slate-950"
                                                    />
                                                </label>
                                                <label className="space-y-1 text-xs font-black uppercase tracking-[0.12em] text-slate-500">
                                                    Puntos por referido
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={settingsForm.data.points_per_qualified_referral}
                                                        onChange={(event) => settingsForm.setData('points_per_qualified_referral', event.target.value)}
                                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold normal-case tracking-normal text-slate-950"
                                                    />
                                                </label>
                                            </div>
                                            <textarea
                                                value={settingsForm.data.points_description}
                                                onChange={(event) => settingsForm.setData('points_description', event.target.value)}
                                                className="min-h-[88px] w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-400"
                                                placeholder="Describe como se podran canjear en el futuro."
                                            />
                                            <button className="w-full rounded-xl bg-sky-700 px-4 py-2 text-sm font-black text-white transition hover:bg-sky-800">
                                                Guardar MindPoints
                                            </button>
                                        </form>

                                        <div className="mt-5 rounded-xl border border-slate-200">
                                            <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                                <p className="text-sm font-black text-slate-950">Saldos recientes</p>
                                                <p className="text-xs font-bold text-slate-500">{stats.point_accounts || 0} cuentas</p>
                                            </div>
                                            <div className="divide-y divide-slate-100">
                                                {pointAccounts.length === 0 ? (
                                                    <p className="px-4 py-5 text-sm text-slate-500">Aun no hay saldos acumulados.</p>
                                                ) : pointAccounts.map((account) => (
                                                    <div key={account.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-black text-slate-950">{account.user?.name || 'Sin nombre'}</p>
                                                            <p className="truncate text-xs text-slate-500">{account.user?.email}</p>
                                                        </div>
                                                        <p className="shrink-0 text-sm font-black text-sky-700">{account.balance_points} pts</p>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {activeConfigTab === 'months' && (
                                    <div>
                                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <p className="text-sm font-black text-slate-950">Reglas de meses gratis</p>
                                            <p className="mt-1 text-sm leading-relaxed text-slate-600">
                                                Estas reglas aplican solo para psicologos que eligen la modalidad de meses gratis.
                                            </p>
                                        </div>

                                        <div className="mt-4 space-y-3">
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
                                    </div>
                                )}

                                {activeConfigTab === 'pending' && (
                                    <div className="space-y-3">
                                        {rewards.length === 0 ? (
                                            <p className="rounded-xl border border-slate-200 px-4 py-5 text-sm text-slate-500">No hay recompensas pendientes o recientes.</p>
                                        ) : rewards.map((reward) => (
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
                                )}
                            </div>
                    </section>
                    )}
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

function PageTabButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`border-b-2 px-1 pb-3 text-sm font-black transition ${
                active
                    ? 'border-sky-700 text-sky-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
            }`}
        >
            {children}
        </button>
    );
}

function TabButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-xl px-3 py-2 text-sm font-black transition ${
                active
                    ? 'bg-white text-sky-700 shadow-sm'
                    : 'text-slate-500 hover:bg-white/70 hover:text-slate-800'
            }`}
        >
            {children}
        </button>
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
