import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

const statusClasses = {
    paid: 'bg-green-100 text-green-800',
    succeeded: 'bg-green-100 text-green-800',
    completed: 'bg-green-100 text-green-800',
    processing: 'bg-blue-100 text-blue-800',
    voucher_generated: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-orange-100 text-orange-800',
    requires_payment_method: 'bg-orange-100 text-orange-800',
    requires_action: 'bg-orange-100 text-orange-800',
    failed: 'bg-red-100 text-red-800',
    payment_failed: 'bg-red-100 text-red-800',
    expired: 'bg-red-100 text-red-800',
    canceled: 'bg-red-100 text-red-800',
};

const sourceLabels = {
    website: 'Sitio web',
    panel: 'Panel',
};

const formatMoney = (value) => {
    const amount = Number(value || 0);

    return amount.toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
};

const formatDate = (date) => {
    if (!date) return 'Sin fecha';

    return new Date(date).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
};

export default function Carts({ auth, carts = [], filters = {}, stats = {} }) {
    const [filter, setFilter] = useState({ search: '', status: 'all' });

    const filteredItems = useMemo(() => {
        const search = filter.search.trim().toLowerCase();

        return carts.filter((item) => {
            const matchesStatus = filter.status === 'all' || item.admin_payment_status === filter.status;
            const searchable = [
                item.id,
                item.user?.name,
                item.patient?.name,
                item.admin_stripe_reference,
                item.payment?.stripe_payment_id,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return matchesStatus && (!search || searchable.includes(search));
        });
    }, [carts, filter]);

    const columns = [
        {
            name: 'ID',
            width: '86px',
            selector: row => row?.id,
            cell: row => <span className="font-semibold text-gray-900">#{row?.id}</span>,
        },
        {
            name: 'Paciente',
            selector: row => row?.patient?.name,
            sortable: true,
            cell: row => row?.patient?.id
                ? <Link className="text-gray-900 hover:text-blue-700" href={`/paciente/${row.patient.id}`}>{row.patient.name}</Link>
                : <span className="text-gray-400">Sin paciente</span>,
        },
        {
            name: 'Psicologo',
            selector: row => row?.user?.name,
            sortable: true,
            cell: row => row?.user?.id
                ? <Link className="text-gray-900 hover:text-blue-700" href={`/psicologo/${row.user.id}`}>{row.user.name}</Link>
                : <span className="text-gray-400">Sin psicologo</span>,
        },
        {
            name: 'Pago',
            selector: row => row?.admin_payment_status,
            sortable: true,
            cell: row => (
                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses[row?.admin_payment_status] || statusClasses.pending}`}>
                    {row?.admin_payment_label || 'Pendiente'}
                </span>
            ),
        },
        {
            name: 'Metodo',
            selector: row => row?.admin_payment_method,
            cell: row => <span className="capitalize">{row?.admin_payment_method || 'Sin intento'}</span>,
        },
        {
            name: 'Monto',
            selector: row => Number(row?.admin_amount || 0),
            sortable: true,
            right: true,
            cell: row => <span className="font-semibold">{formatMoney(row?.admin_amount)}</span>,
        },
        {
            name: 'Stripe',
            selector: row => row?.admin_stripe_reference,
            grow: 1.4,
            cell: row => row?.admin_stripe_reference
                ? <span className="max-w-[12rem] truncate font-mono text-xs text-gray-700" title={row.admin_stripe_reference}>{row.admin_stripe_reference}</span>
                : <span className="text-gray-400">Sin referencia</span>,
        },
        {
            name: 'Origen',
            selector: row => row?.source,
            cell: row => sourceLabels[row?.source] || row?.source || 'Sin origen',
        },
        {
            name: 'Sesion',
            selector: row => row?.fecha,
            sortable: true,
            cell: row => (
                <div className="text-sm">
                    <div>{formatDate(row?.fecha)}</div>
                    <div className="text-xs text-gray-500">{row?.hora || 'Sin hora'} · {row?.duracion || 1} h</div>
                </div>
            ),
        },
    ];

    const changeSource = (event) => {
        router.get('/carts', { source: event.target.value }, { preserveState: false, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Pagos del sitio web</h2>}
        >
            <Head title="Pagos del sitio web" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-3 sm:grid-cols-4">
                        <StatCard label="Total" value={stats.total || 0} />
                        <StatCard label="Pagados" value={stats.pagado || 0} tone="green" />
                        <StatCard label="Pendientes" value={stats.pendiente || 0} tone="yellow" />
                        <StatCard label="Fallidos" value={stats.fallido || 0} tone="red" />
                    </div>

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 className="font-semibold text-gray-900">Carts y pagos Stripe</h3>
                                <p className="text-sm text-gray-500">Por defecto se muestran solo los carts creados desde el sitio web.</p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <select
                                    value={filters.source || 'website'}
                                    onChange={changeSource}
                                    className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="website">Sitio web</option>
                                    <option value="panel">Panel</option>
                                    <option value="all">Todos</option>
                                </select>
                                <select
                                    value={filter.status}
                                    onChange={(event) => setFilter((prev) => ({ ...prev, status: event.target.value }))}
                                    className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="all">Todos los estatus</option>
                                    <option value="paid">Pagados</option>
                                    <option value="processing">Procesando</option>
                                    <option value="voucher_generated">Voucher generado</option>
                                    <option value="pending">Pendientes</option>
                                    <option value="failed">Fallidos</option>
                                    <option value="expired">Expirados</option>
                                    <option value="canceled">Cancelados</option>
                                </select>
                                <input
                                    type="search"
                                    placeholder="Buscar paciente, psicologo o Stripe"
                                    value={filter.search}
                                    onChange={(event) => setFilter((prev) => ({ ...prev, search: event.target.value }))}
                                    className="min-w-72 rounded-md border border-gray-300 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            data={filteredItems}
                            pagination
                            paginationPerPage={10}
                            persistTableHead
                            noDataComponent="No hay carts para estos filtros."
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, tone = 'gray' }) {
    const tones = {
        gray: 'bg-gray-50 text-gray-900',
        green: 'bg-green-50 text-green-900',
        yellow: 'bg-yellow-50 text-yellow-900',
        red: 'bg-red-50 text-red-900',
    };

    return (
        <div className={`rounded-lg px-5 py-4 ${tones[tone]}`}>
            <div className="text-sm font-medium opacity-70">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}
