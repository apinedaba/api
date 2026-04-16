import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import Modal from '@/Components/Modal';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import DataTable from 'react-data-table-component';

const emptyCoupon = {
    user_id: '',
    code: '',
    name: '',
    description: '',
    discount_type: 'percent',
    discount_value: '',
    applies_to: 'all',
    starts_at: '',
    ends_at: '',
    max_redemptions: '',
    is_active: true,
};

const discountLabel = (coupon) =>
    coupon.discount_type === 'percent'
        ? `${Number(coupon.discount_value || 0)}%`
        : `$${Number(coupon.discount_value || 0).toFixed(2)}`;

const scopeLabels = {
    all: 'Sesiones y paquetes',
    sessions: 'Solo sesiones',
    packages: 'Solo paquetes',
};

export default function Coupons({ auth, coupons, psychologists }) {
    const [showModal, setShowModal] = useState(false);
    const [editingCoupon, setEditingCoupon] = useState(null);

    const openCreate = () => {
        setEditingCoupon(null);
        setShowModal(true);
    };

    const openEdit = (coupon) => {
        setEditingCoupon(coupon);
        setShowModal(true);
    };

    const deleteCoupon = (coupon) => {
        if (!window.confirm(`Eliminar el cupon ${coupon.code}?`)) return;

        router.delete(route('coupons.destroy', coupon.id), {
            preserveScroll: true,
        });
    };

    const columns = [
        {
            name: 'Cupon',
            selector: row => row.code,
            sortable: true,
            cell: row => (
                <button type="button" onClick={() => openEdit(row)} className="text-left">
                    <span className="block font-bold text-slate-900">{row.code}</span>
                    <span className="text-xs text-slate-500">{row.name}</span>
                </button>
            ),
        },
        {
            name: 'Psicologo',
            selector: row => row.user?.name,
            sortable: true,
            cell: row => (
                <a href={`/psicologo/${row.user_id}`} className="text-blue-700 hover:underline">
                    {row.user?.name || row.user_id}
                </a>
            ),
        },
        {
            name: 'Descuento',
            selector: row => Number(row.discount_value || 0),
            sortable: true,
            cell: row => discountLabel(row),
        },
        {
            name: 'Aplica a',
            selector: row => scopeLabels[row.applies_to] || row.applies_to,
        },
        {
            name: 'Vigencia',
            selector: row => row.ends_at || '',
            cell: row => `${row.starts_at || 'Ahora'} - ${row.ends_at || 'Sin fin'}`,
        },
        {
            name: 'Usos',
            selector: row => row.redeemed_count,
            cell: row => `${row.redeemed_count || 0}${row.max_redemptions ? ` / ${row.max_redemptions}` : ''}`,
        },
        {
            name: 'Estado',
            cell: row => (
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${row.is_currently_available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                    {row.is_currently_available ? 'Disponible' : 'Inactivo'}
                </span>
            ),
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex gap-3">
                    <button type="button" onClick={() => openEdit(row)} className="text-blue-700 hover:underline">Editar</button>
                    <button type="button" onClick={() => deleteCoupon(row)} className="text-red-600 hover:underline">Eliminar</button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Cupones de descuento</h2>}
        >
            <Head title="Cupones" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-blue-100 bg-blue-50 p-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-blue-700">Revenue ops</p>
                                <h1 className="text-2xl font-black text-slate-950">Cupones por psicologo</h1>
                                <p className="mt-1 max-w-2xl text-sm text-slate-600">
                                    Crea descuentos con vigencia para sesiones, paquetes o ambos. Cada codigo es unico por psicologo.
                                </p>
                            </div>
                            <PrimaryButton onClick={openCreate}>Nuevo cupon</PrimaryButton>
                        </div>
                    </section>

                    <section className="rounded-2xl bg-white p-4 shadow-sm">
                        <DataTable
                            columns={columns}
                            data={coupons || []}
                            pagination
                            paginationPerPage={12}
                            persistTableHead
                            noDataComponent="No hay cupones creados."
                        />
                    </section>
                </div>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="2xl">
                <CouponForm
                    coupon={editingCoupon}
                    psychologists={psychologists}
                    onClose={() => setShowModal(false)}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}

function CouponForm({ coupon, psychologists, onClose }) {
    const { data, setData, post, put, processing, errors } = useForm({
        ...emptyCoupon,
        ...coupon,
        user_id: coupon?.user_id || '',
        discount_value: coupon?.discount_value || '',
        max_redemptions: coupon?.max_redemptions || '',
    });

    const submit = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (coupon?.id) {
            put(route('coupons.update', coupon.id), options);
            return;
        }

        post(route('coupons.store'), options);
    };

    return (
        <form onSubmit={submit} className="space-y-4 p-6">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">
                    {coupon?.id ? 'Editar cupon' : 'Nuevo cupon'}
                </p>
                <h2 className="text-xl font-bold text-slate-950">Configuracion del descuento</h2>
            </div>

            <Field label="Psicologo" error={errors.user_id}>
                <select value={data.user_id} onChange={(event) => setData('user_id', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                    <option value="">Selecciona psicologo</option>
                    {psychologists?.map((psychologist) => (
                        <option key={psychologist.id} value={psychologist.id}>{psychologist.name} - {psychologist.email}</option>
                    ))}
                </select>
            </Field>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Codigo" error={errors.code}>
                    <input value={data.code} onChange={(event) => setData('code', event.target.value.toUpperCase())} className="w-full rounded-lg border-slate-200 text-sm" placeholder="MIND10" />
                </Field>
                <Field label="Nombre interno" error={errors.name}>
                    <input value={data.name} onChange={(event) => setData('name', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Promo Facebook Abril" />
                </Field>
            </div>

            <Field label="Descripcion" error={errors.description}>
                <textarea value={data.description || ''} onChange={(event) => setData('description', event.target.value)} rows={3} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>

            <div className="grid gap-4 md:grid-cols-3">
                <Field label="Tipo" error={errors.discount_type}>
                    <select value={data.discount_type} onChange={(event) => setData('discount_type', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                        <option value="percent">Porcentaje</option>
                        <option value="fixed">Monto fijo</option>
                    </select>
                </Field>
                <Field label="Valor" error={errors.discount_value}>
                    <input type="number" min="0" step="0.01" value={data.discount_value} onChange={(event) => setData('discount_value', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Aplica a" error={errors.applies_to}>
                    <select value={data.applies_to} onChange={(event) => setData('applies_to', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                        <option value="all">Sesiones y paquetes</option>
                        <option value="sessions">Solo sesiones</option>
                        <option value="packages">Solo paquetes</option>
                    </select>
                </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Field label="Inicio" error={errors.starts_at}>
                    <input type="date" value={data.starts_at || ''} onChange={(event) => setData('starts_at', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Fin" error={errors.ends_at}>
                    <input type="date" value={data.ends_at || ''} onChange={(event) => setData('ends_at', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Maximo de usos" error={errors.max_redemptions}>
                    <input type="number" min="1" value={data.max_redemptions || ''} onChange={(event) => setData('max_redemptions', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Sin limite" />
                </Field>
            </div>

            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={data.is_active} onChange={() => setData('is_active', !data.is_active)} className="rounded border-slate-300" />
                Cupon activo
            </label>

            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onClick={onClose} className="rounded-lg px-4 py-2 text-sm text-slate-600">Cancelar</button>
                <PrimaryButton disabled={processing}>{processing ? 'Guardando...' : 'Guardar cupon'}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block text-sm font-semibold text-slate-700">
            <span className="mb-1 block">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs font-normal text-red-600">{error}</span>}
        </label>
    );
}
