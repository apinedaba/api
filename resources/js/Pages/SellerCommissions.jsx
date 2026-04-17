import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const money = (value) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0));

const milestoneLabels = {
    activation: 'Activacion',
    month_2: 'Mes 2 activo',
    month_6: 'Mes 6 activo',
};

export default function SellerCommissions({ auth, cutDate, pendingBySeller = [], items = [], totals = {} }) {
    const [selectedItems, setSelectedItems] = useState([]);
    const { data, setData, post, processing } = useForm({ cut_date: cutDate });

    const pendingItems = useMemo(
        () => items.filter((item) => item.status === 'pending'),
        [items]
    );

    const toggleItem = (id) => {
        setSelectedItems((current) =>
            current.includes(id)
                ? current.filter((itemId) => itemId !== id)
                : [...current, id]
        );
    };

    const markSelectedAsPaid = () => {
        if (!selectedItems.length) return;

        router.patch(route('seller-commissions.mark-paid'), {
            item_ids: selectedItems,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelectedItems([]),
        });
    };

    const generateCut = (event) => {
        event.preventDefault();
        post(route('seller-commissions.generate'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Pagos a vendedores</h2>}
        >
            <Head title="Pagos a vendedores" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl bg-slate-900 p-6 text-white shadow-sm">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-[0.28em] text-cyan-200">Corte mensual</p>
                                <h1 className="mt-2 text-3xl font-black">Comisiones pendientes: {money(totals.pending)}</h1>
                                <p className="mt-2 max-w-3xl text-sm text-slate-300">
                                    Regla vigente: $50 al activar cuenta, $20 si sigue activo al mes 2 y $30 si sigue activo al mes 6.
                                    El corte automatico corre el dia 25 de cada mes.
                                </p>
                            </div>

                            <form onSubmit={generateCut} className="flex flex-wrap items-end gap-3">
                                <label className="text-xs text-slate-200">
                                    Fecha de corte
                                    <input
                                        type="date"
                                        value={data.cut_date || ''}
                                        onChange={(event) => setData('cut_date', event.target.value)}
                                        className="mt-1 block rounded-lg border-0 px-3 py-2 text-sm text-slate-900"
                                    />
                                </label>
                                <PrimaryButton disabled={processing}>
                                    Actualizar corte
                                </PrimaryButton>
                            </form>
                        </div>
                    </section>

                    <section className="grid gap-4 md:grid-cols-3">
                        <StatCard label="Pendiente de pago" value={money(totals.pending)} />
                        <StatCard label="Ya pagado historico" value={money(totals.paid)} />
                        <StatCard label="Comisiones pendientes" value={totals.pending_items || 0} />
                    </section>

                    <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-gray-900">Resumen por vendedor</h2>
                                <p className="text-sm text-gray-500">Monto total que se debe pagar por vendedor en cortes pendientes.</p>
                            </div>
                            <PrimaryButton onClick={markSelectedAsPaid} disabled={!selectedItems.length}>
                                Marcar seleccionadas como pagadas
                            </PrimaryButton>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            {pendingBySeller.map((row) => (
                                <div key={row.vendedor_id} className="rounded-xl border border-gray-100 p-4">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="font-semibold text-gray-900">{row.vendedor?.nombre}</h3>
                                            <p className="text-sm text-gray-500">{row.vendedor?.email}</p>
                                        </div>
                                        <span className="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-700">
                                            {money(row.total_pending)}
                                        </span>
                                    </div>
                                    <div className="mt-4 grid grid-cols-4 gap-2 text-center text-xs">
                                        <MiniStat label="Items" value={row.items_count} />
                                        <MiniStat label="$50" value={row.activation_count} />
                                        <MiniStat label="$20" value={row.month_2_count} />
                                        <MiniStat label="$30" value={row.month_6_count} />
                                    </div>
                                </div>
                            ))}
                            {!pendingBySeller.length && (
                                <p className="text-sm text-gray-500">No hay pagos pendientes para este corte.</p>
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        <div className="border-b border-gray-100 p-6">
                            <h2 className="text-lg font-bold text-gray-900">Detalle de comisiones</h2>
                            <p className="text-sm text-gray-500">Cada fila representa una regla cumplida por un psicologo referido.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Pagar</th>
                                        <th className="px-4 py-3">Vendedor</th>
                                        <th className="px-4 py-3">Psicologo</th>
                                        <th className="px-4 py-3">Regla</th>
                                        <th className="px-4 py-3">Monto</th>
                                        <th className="px-4 py-3">Corte</th>
                                        <th className="px-4 py-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                {item.status === 'pending' && (
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedItems.includes(item.id)}
                                                        onChange={() => toggleItem(item.id)}
                                                        className="rounded border-gray-300"
                                                    />
                                                )}
                                            </td>
                                            <td className="px-4 py-3">{item.vendedor?.nombre}</td>
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-gray-900">{item.psychologist?.name}</p>
                                                <p className="text-xs text-gray-500">{item.psychologist?.email}</p>
                                            </td>
                                            <td className="px-4 py-3">{milestoneLabels[item.milestone] || item.milestone}</td>
                                            <td className="px-4 py-3 font-bold">{money(item.amount)}</td>
                                            <td className="px-4 py-3">{item.cut_date || item.eligible_at}</td>
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full px-3 py-1 text-xs font-bold ${item.status === 'paid' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'}`}>
                                                    {item.status === 'paid' ? 'Pagado' : 'Pendiente'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                    {!items.length && (
                                        <tr>
                                            <td colSpan="7" className="px-4 py-8 text-center text-gray-500">Aun no hay comisiones generadas.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p className="text-xs uppercase tracking-[0.2em] text-gray-500">{label}</p>
            <p className="mt-2 text-2xl font-black text-gray-900">{value}</p>
        </div>
    );
}

function MiniStat({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 p-2">
            <p className="font-bold text-gray-900">{value}</p>
            <p className="text-gray-500">{label}</p>
        </div>
    );
}
