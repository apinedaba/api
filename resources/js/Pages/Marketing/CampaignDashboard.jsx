import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DataTable from 'react-data-table-component';

const money = (value) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));

const STATUS_CONFIG = {
    paid: { label: 'Pagado', classes: 'bg-emerald-100 text-emerald-700' },
    active: { label: 'Activo', classes: 'bg-blue-100 text-blue-700' },
    finished: { label: 'Finalizado', classes: 'bg-slate-100 text-slate-500' },
    recruiting: { label: 'Reclutando', classes: 'bg-amber-100 text-amber-700' },
    full: { label: 'Completo', classes: 'bg-violet-100 text-violet-700' },
    completed: { label: 'Completado', classes: 'bg-slate-100 text-slate-500' },
};

const StatusBadge = ({ status }) => {
    const config = STATUS_CONFIG[status] ?? { label: status, classes: 'bg-slate-100 text-slate-600' };
    return (
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${config.classes}`}>
            {config.label}
        </span>
    );
};

export default function CampaignDashboard({ auth, requests = [], groups = [] }) {
    const [activeTab, setActiveTab] = useState('requests');
    const [briefRequest, setBriefRequest] = useState(null);

    // Contadores para stats
    const paidCount = requests.filter((r) => r.status === 'paid').length;
    const activeCount = requests.filter((r) => r.status === 'active').length;
    const groupsOpen = groups.filter((g) => g.status === 'recruiting').length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">MindBoost — Campañas</h2>}
        >
            <Head title="MindBoost · Campañas" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Hero oscuro (igual a SellerCommissions) */}
                    <section className="rounded-2xl bg-slate-900 p-6 text-white shadow-sm">
                        <p className="text-xs uppercase tracking-[0.28em] text-violet-300">MindBoost</p>
                        <h1 className="mt-2 text-3xl font-black">Monitor de campañas</h1>
                        <p className="mt-2 max-w-3xl text-sm text-slate-300">
                            Visualiza las solicitudes pagadas, activas y el estado de las CombiMindMeet grupales en tiempo real.
                        </p>
                    </section>

                    {/* Stats */}
                    <section className="grid gap-4 md:grid-cols-3">
                        <StatCard label="Solicitudes pagadas" value={paidCount} />
                        <StatCard label="Campañas activas" value={activeCount} />
                        <StatCard label="Combis reclutando" value={groupsOpen} />
                    </section>

                    {/* Tabs */}
                    <div className="flex gap-1 rounded-xl bg-slate-100 p-1">
                        <TabButton active={activeTab === 'requests'} onClick={() => setActiveTab('requests')}>
                            Solicitudes ({requests.length})
                        </TabButton>
                        <TabButton active={activeTab === 'groups'} onClick={() => setActiveTab('groups')}>
                            CombiMindMeet ({groups.length})
                        </TabButton>
                    </div>

                    {/* ── Tab: Solicitudes ── */}
                    {activeTab === 'requests' && (
                        <section className="rounded-2xl bg-white p-4 shadow-sm">
                            <RequestsTable requests={requests} onViewBrief={setBriefRequest} />
                        </section>
                    )}

                    {/* ── Tab: Campañas grupales ── */}
                    {activeTab === 'groups' && (
                        <section className="space-y-4">
                            {groups.length === 0 && (
                                <div className="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                                    No hay campañas grupales activas.
                                </div>
                            )}
                            {groups.map((group) => (
                                <GroupCard key={group.id} group={group} onViewBrief={setBriefRequest} />
                            ))}
                        </section>
                    )}
                </div>
            </div>

            {/* Modal: Brief de la solicitud */}
            <Modal show={!!briefRequest} onClose={() => setBriefRequest(null)} maxWidth="lg">
                {briefRequest && <BriefModal request={briefRequest} onClose={() => setBriefRequest(null)} />}
            </Modal>
        </AuthenticatedLayout>
    );
}

// ── Tabla de solicitudes ──────────────────────────────────────────────────────

function RequestsTable({ requests, onViewBrief }) {
    const activateCampaign = (row) => {
        const campaignUrl = prompt('Link público de la campaña lanzada (opcional):', row.campaign_url ?? '');

        if (campaignUrl === null) {
            return;
        }

        if (!confirm(`¿Activar la campaña #${row.id} por 30 días?`)) {
            return;
        }

        router.post(route('marketing.campaigns.activate', row.id), {
            duration_days: 30,
            campaign_url: campaignUrl,
        }, {
            preserveScroll: true,
        });
    };

    const finishCampaign = (row) => {
        if (!confirm(`¿Finalizar la campaña #${row.id}? El psicólogo podrá contratar otra campaña.`)) {
            return;
        }

        router.post(route('marketing.campaigns.finish', row.id), {}, {
            preserveScroll: true,
        });
    };

    const columns = [
        {
            name: 'Psicólogo',
            selector: (row) => row.user?.name ?? '',
            sortable: true,
            cell: (row) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.user?.name ?? '—'}</p>
                    <p className="text-xs text-slate-400">{row.user?.email}</p>
                </div>
            ),
        },
        {
            name: 'Paquete',
            selector: (row) => row.marketing_package?.name ?? '',
            sortable: true,
            cell: (row) => (
                <div>
                    <p className="text-sm font-medium text-slate-800">{row.marketing_package?.name ?? '—'}</p>
                    <p className="text-xs text-slate-400">
                        {row.marketing_package?.type === 'group' ? 'Grupal' : 'Individual'} ·{' '}
                        {money(row.marketing_package?.price)}
                    </p>
                </div>
            ),
        },
        {
            name: 'Status',
            selector: (row) => row.status,
            sortable: true,
            cell: (row) => <StatusBadge status={row.status} />,
        },
        {
            name: 'Circulación',
            selector: (row) => row.starts_at ?? '',
            sortable: true,
            cell: (row) => (
                <div className="text-sm text-slate-600">
                    {row.starts_at ? (
                        <>
                            <p className="font-semibold text-slate-800">{row.starts_at}</p>
                            <p className="text-xs text-slate-400">Termina {row.ends_at ?? 'por definir'}</p>
                        </>
                    ) : (
                        <span className="text-slate-400">Sin iniciar</span>
                    )}
                </div>
            ),
        },
        {
            name: 'Fecha',
            selector: (row) => row.created_at,
            sortable: true,
            cell: (row) => <span className="text-sm text-slate-600">{row.created_at}</span>,
        },
        {
            name: 'Brief',
            cell: (row) => (
                <button
                    type="button"
                    onClick={() => onViewBrief(row)}
                    className="rounded-lg bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 transition hover:bg-violet-100"
                >
                    Ver brief
                </button>
            ),
        },
        {
            name: 'Acciones',
            cell: (row) => (
                <div className="flex flex-col gap-1.5">
                    {row.status === 'paid' && (
                        <button
                            type="button"
                            onClick={() => activateCampaign(row)}
                            className="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700"
                        >
                            Activar 30 días
                        </button>
                    )}
                    {row.status === 'active' && (
                        <button
                            type="button"
                            onClick={() => finishCampaign(row)}
                            className="rounded-lg bg-slate-800 px-3 py-1 text-xs font-semibold text-white transition hover:bg-slate-900"
                        >
                            Finalizar
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <DataTable
            columns={columns}
            data={requests}
            pagination
            paginationPerPage={12}
            persistTableHead
            noDataComponent="No hay solicitudes pagadas o activas."
        />
    );
}

// ── Tarjeta de campaña grupal ─────────────────────────────────────────────────

function GroupCard({ group, onViewBrief }) {
    const max = group.package?.max_slots ?? 0;
    const filled = group.paid_slots ?? 0;
    const missing = group.missing_slots ?? Math.max(max - filled, 0);
    const pct = max > 0 ? Math.round((filled / max) * 100) : 0;

    const barColor =
        pct >= 100 ? 'bg-violet-500' : pct >= 60 ? 'bg-blue-500' : 'bg-emerald-500';

    const activateGroup = () => {
        const campaignUrl = prompt('Link público de la campaña lanzada (opcional):', group.campaign_url ?? '');

        if (campaignUrl === null) {
            return;
        }

        if (!confirm(`¿Activar CombiMindMeet #${group.id} para ${filled} psicólogos?`)) {
            return;
        }

        router.post(route('marketing.groups.activate', group.id), {
            duration_days: 30,
            campaign_url: campaignUrl,
        }, {
            preserveScroll: true,
        });
    };

    const finishGroup = () => {
        if (!confirm(`¿Finalizar CombiMindMeet #${group.id}?`)) {
            return;
        }

        router.post(route('marketing.groups.finish', group.id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="flex-1">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-violet-700">
                        CombiMindMeet #{group.id}
                    </p>
                    <h3 className="mt-1 text-lg font-bold text-slate-900">
                        {group.package?.name ?? 'Paquete eliminado'}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        Creada el {group.created_at} · {money(group.package?.price)} por psicólogo
                    </p>
                </div>
                <div className="flex flex-col items-start gap-2 md:items-end">
                    <StatusBadge status={group.status} />
                    {group.status === 'active' && (
                        <button
                            type="button"
                            onClick={finishGroup}
                            className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-900"
                        >
                            Finalizar Combi
                        </button>
                    )}
                    {group.status !== 'active' && group.status !== 'completed' && (
                        <button
                            type="button"
                            onClick={activateGroup}
                            disabled={!group.can_activate}
                            className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            Activar Combi
                        </button>
                    )}
                </div>
            </div>

            {/* Barra de progreso */}
            <div className="mt-4">
                <div className="mb-1 flex items-center justify-between text-sm">
                    <span className="font-semibold text-slate-700">
                        {filled} / {max} psicólogos
                    </span>
                    <span className="font-bold text-slate-900">
                        {missing === 0 ? 'Lista para publicar' : `Faltan ${missing}`}
                    </span>
                </div>
                <div className="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                    <div
                        className={`h-3 rounded-full transition-all ${barColor}`}
                        style={{ width: `${Math.min(pct, 100)}%` }}
                    />
                </div>
                {max > 0 && (
                    <div className="mt-2 flex gap-1">
                        {Array.from({ length: max }).map((_, i) => (
                            <div
                                key={i}
                                className={`h-2 flex-1 rounded-full ${i < filled ? barColor : 'bg-slate-200'
                                    }`}
                            />
                        ))}
                    </div>
                )}
            </div>

            {!group.can_activate && group.status !== 'active' && group.status !== 'completed' && (
                <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                    Esta CombiMindMeet se publica cuando se llenen todos los espacios.
                </p>
            )}

            <div className="mt-5 overflow-hidden rounded-xl border border-slate-100">
                <div className="grid grid-cols-[1.3fr_0.7fr_0.7fr] bg-slate-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                    <span>Psicólogo</span>
                    <span>Status</span>
                    <span className="text-right">Brief</span>
                </div>
                {(group.members ?? []).length === 0 ? (
                    <div className="px-4 py-4 text-sm text-slate-400">Sin participantes pagados todavía.</div>
                ) : (
                    group.members.map((member) => (
                        <div
                            key={member.id}
                            className="grid grid-cols-[1.3fr_0.7fr_0.7fr] items-center border-t border-slate-100 px-4 py-3 text-sm"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-slate-900">{member.user?.name ?? '—'}</p>
                                <p className="truncate text-xs text-slate-400">{member.user?.email}</p>
                            </div>
                            <StatusBadge status={member.status} />
                            <div className="text-right">
                                <button
                                    type="button"
                                    onClick={() => onViewBrief(member)}
                                    className="rounded-lg bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 transition hover:bg-violet-100"
                                >
                                    Ver brief
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

// ── Modal: Brief ──────────────────────────────────────────────────────────────

function BriefModal({ request, onClose }) {
    const audience = request.target_audience ?? {};
    const locations = request.locations ?? [];
    const [campaignUrl, setCampaignUrl] = useState(request.campaign_url ?? '');
    const [brief, setBrief] = useState({
        age_range: audience.age_range ?? '',
        gender: audience.gender ?? '',
        specialty_focus: audience.specialty_focus ?? '',
        interests: (audience.interests ?? []).join(', '),
        locations: locations.join(', '),
    });

    const saveCampaignUrl = (event) => {
        event.preventDefault();

        router.post(route('marketing.campaigns.link', request.id), {
            campaign_url: campaignUrl,
        }, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const saveBrief = (event) => {
        event.preventDefault();

        router.post(route('marketing.campaigns.brief', request.id), {
            target_audience: {
                age_range: brief.age_range,
                gender: brief.gender || null,
                specialty_focus: brief.specialty_focus,
                interests: splitList(brief.interests),
            },
            locations: splitList(brief.locations),
        }, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="space-y-5 p-6">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-violet-700">Brief</p>
                <h2 className="text-xl font-bold text-slate-950">
                    Solicitud #{request.id}
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                    {request.user?.name} · {request.marketing_package?.name}
                </p>
            </div>

            {/* Audiencia */}
            <form onSubmit={saveBrief} className="rounded-xl border border-slate-100 p-4">
                <p className="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Audiencia objetivo</p>
                <div className="grid gap-3 sm:grid-cols-2">
                    <BriefInput
                        label="Rango de edad"
                        value={brief.age_range}
                        onChange={(value) => setBrief((prev) => ({ ...prev, age_range: value }))}
                        placeholder="25-45"
                    />
                    <label className="text-sm">
                        <span className="mb-1 block font-semibold text-slate-500">Género</span>
                        <select
                            value={brief.gender}
                            onChange={(event) => setBrief((prev) => ({ ...prev, gender: event.target.value }))}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-slate-800 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                        >
                            <option value="">Sin filtro</option>
                            <option value="femenino">Femenino</option>
                            <option value="masculino">Masculino</option>
                            <option value="todos">Todos</option>
                        </select>
                    </label>
                    <div className="sm:col-span-2">
                        <BriefInput
                            label="Especialidad"
                            value={brief.specialty_focus}
                            onChange={(value) => setBrief((prev) => ({ ...prev, specialty_focus: value }))}
                            placeholder="Terapia cognitivo-conductual"
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <BriefInput
                            label="Intereses / motivos"
                            value={brief.interests}
                            onChange={(value) => setBrief((prev) => ({ ...prev, interests: value }))}
                            placeholder="Ansiedad, Depresión"
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <BriefInput
                            label="Ubicaciones"
                            value={brief.locations}
                            onChange={(value) => setBrief((prev) => ({ ...prev, locations: value }))}
                            placeholder="CDMX, Monterrey"
                        />
                    </div>
                </div>
                <div className="mt-3 flex justify-end">
                    <button
                        type="submit"
                        className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        Guardar brief
                    </button>
                </div>
            </form>

            {/* Ubicaciones */}
            <div className="rounded-xl border border-amber-100 bg-amber-50 p-4">
                <p className="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Ubicaciones</p>
                {locations.length === 0 ? (
                    <p className="text-sm text-amber-700">Sin ubicaciones especificadas.</p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {locations.map((loc) => (
                            <span
                                key={loc}
                                className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"
                            >
                                {loc}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <form onSubmit={saveCampaignUrl} className="rounded-xl border border-slate-100 p-4">
                <label className="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">
                    Link de campaña publicada
                </label>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <input
                        type="url"
                        value={campaignUrl}
                        onChange={(event) => setCampaignUrl(event.target.value)}
                        placeholder="https://facebook.com/..."
                        className="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                    />
                    <button
                        type="submit"
                        className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Guardar link
                    </button>
                </div>
                {request.campaign_url && (
                    <a
                        href={request.campaign_url}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-2 inline-block text-xs font-semibold text-violet-700 hover:underline"
                    >
                        Abrir campaña actual
                    </a>
                )}
            </form>

            <div className="flex justify-end border-t border-slate-100 pt-3">
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                    Cerrar
                </button>
            </div>
        </div>
    );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function TabButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition ${active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                }`}
        >
            {children}
        </button>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{label}</p>
            <p className="mt-2 text-3xl font-black text-slate-900">{value}</p>
        </div>
    );
}

function BriefRow({ label, value }) {
    return (
        <div className="flex gap-3 text-sm">
            <dt className="w-28 shrink-0 font-semibold text-slate-500">{label}</dt>
            <dd className="text-slate-800">{value}</dd>
        </div>
    );
}

function BriefInput({ label, value, onChange, placeholder }) {
    return (
        <label className="text-sm">
            <span className="mb-1 block font-semibold text-slate-500">{label}</span>
            <input
                type="text"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-slate-800 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
            />
        </label>
    );
}

function splitList(value) {
    return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
}
