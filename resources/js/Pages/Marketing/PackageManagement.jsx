import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import Modal from '@/Components/Modal';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import DataTable from 'react-data-table-component';

const money = (value) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));

const emptyPackage = {
    name: '',
    slug: '',
    description: '',
    type: 'individual',
    price: '',
    max_slots: '',
    stripe_product_id: '',
    is_active: true,
};

const typeLabel = { individual: 'Individual', group: 'Grupal (CombiMindMeet)' };

export default function PackageManagement({ auth, packages = [] }) {
    const [showModal, setShowModal] = useState(false);
    const [editingPackage, setEditingPackage] = useState(null);

    const openCreate = () => {
        setEditingPackage(null);
        setShowModal(true);
    };

    const openEdit = (pkg) => {
        setEditingPackage(pkg);
        setShowModal(true);
    };

    const togglePackageStatus = (pkg) => {
        if (!confirm(`¿${pkg.is_active ? 'Desactivar' : 'Activar'} "${pkg.name}"?`)) {
            return;
        }

        router.put(route('marketing.packages.update', pkg.id), {
            ...pkg,
            is_active: !pkg.is_active,
        }, {
            preserveScroll: true,
            onError: () => alert('Error al cambiar estado del paquete'),
        });
    };

    const columns = [
        {
            name: 'Paquete',
            selector: (row) => row.name,
            sortable: true,
            cell: (row) => (
                <button type="button" onClick={() => openEdit(row)} className="text-left">
                    <span className="block font-bold text-slate-900">{row.name}</span>
                    <span className="text-xs text-slate-400">{row.slug}</span>
                </button>
            ),
        },
        {
            name: 'Tipo',
            selector: (row) => row.type,
            sortable: true,
            cell: (row) => (
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold ${row.type === 'group'
                        ? 'bg-violet-100 text-violet-700'
                        : 'bg-blue-100 text-blue-700'
                        }`}
                >
                    {typeLabel[row.type] ?? row.type}
                </span>
            ),
        },
        {
            name: 'Precio',
            selector: (row) => Number(row.price),
            sortable: true,
            cell: (row) => (
                <div>
                    <span className="font-semibold">{money(row.price)}</span>
                    {row.type === 'group' && (
                        <p className="text-xs text-slate-400">por psicólogo</p>
                    )}
                </div>
            ),
        },
        {
            name: 'Max slots',
            selector: (row) => row.max_slots,
            cell: (row) =>
                row.max_slots ? (
                    <span className="font-medium">{row.max_slots} psicólogos</span>
                ) : (
                    <span className="text-slate-400">—</span>
                ),
        },
        {
            name: 'Stripe Product ID',
            selector: (row) => row.stripe_product_id,
            cell: (row) =>
                row.stripe_product_id ? (
                    <span className="font-mono text-xs text-slate-500">{row.stripe_product_id}</span>
                ) : (
                    <span className="text-slate-300 text-xs">Sin asignar</span>
                ),
        },
        {
            name: 'Estado',
            cell: (row) => (
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold ${row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'
                        }`}
                >
                    {row.is_active ? 'Activo' : 'Inactivo'}
                </span>
            ),
        },
        {
            name: 'Acciones',
            cell: (row) => (
                <div className="flex gap-2">
                    <button type="button" onClick={() => openEdit(row)} className="text-blue-700 hover:underline text-sm">
                        Editar
                    </button>
                    <button
                        type="button"
                        onClick={() => togglePackageStatus(row)}
                        className={`text-sm ${row.is_active ? 'text-red-600 hover:underline' : 'text-green-600 hover:underline'}`}
                    >
                        {row.is_active ? 'Desactivar' : 'Activar'}
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">MindBoost — Paquetes de marketing</h2>}
        >
            <Head title="MindBoost · Paquetes" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Hero */}
                    <section className="rounded-2xl border border-violet-100 bg-violet-50 p-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-violet-700">MindBoost</p>
                                <h1 className="text-2xl font-black text-slate-950">Gestión de paquetes</h1>
                                <p className="mt-1 max-w-2xl text-sm text-slate-600">
                                    Define los productos de marketing que los psicólogos pueden comprar:
                                    planes individuales o CombiMindMeet grupales.
                                </p>
                            </div>
                            <PrimaryButton onClick={openCreate}>Nuevo paquete</PrimaryButton>
                        </div>
                    </section>

                    {/* Tabla */}
                    <section className="rounded-2xl bg-white p-4 shadow-sm">
                        <DataTable
                            columns={columns}
                            data={packages}
                            pagination
                            paginationPerPage={12}
                            persistTableHead
                            noDataComponent="No hay paquetes creados todavía."
                        />
                    </section>
                </div>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="2xl">
                <PackageForm pkg={editingPackage} onClose={() => setShowModal(false)} />
            </Modal>
        </AuthenticatedLayout>
    );
}

// ── Formulario ────────────────────────────────────────────────────────────────

function PackageForm({ pkg, onClose }) {
    const { data, setData, post, put, processing, errors } = useForm({
        ...emptyPackage,
        ...pkg,
        price: pkg?.price ?? '',
        max_slots: pkg?.max_slots ?? '',
        stripe_product_id: pkg?.stripe_product_id ?? '',
        is_active: pkg?.is_active ?? true,
    });

    const isGroup = data.type === 'group';

    const handleNameChange = (event) => {
        const name = event.target.value;
        setData((prev) => ({
            ...prev,
            name,
            // Auto-genera slug solo en creación
            ...(!pkg?.id ? {
                slug: (name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '') || 'paquete-' + Date.now())
                    .substring(0, 150) // Limitar a 150 caracteres
            } : {}),
        }));
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (pkg?.id) {
            put(route('marketing.packages.update', pkg.id), options);
            return;
        }

        post(route('marketing.packages.store'), options);
    };

    return (
        <form onSubmit={submit} className="space-y-4 p-6">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-violet-700">
                    {pkg?.id ? 'Editar paquete' : 'Nuevo paquete'}
                </p>
                <h2 className="text-xl font-bold text-slate-950">Configuración del paquete</h2>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Nombre" error={errors.name}>
                    <input
                        value={data.name}
                        onChange={handleNameChange}
                        className="w-full rounded-lg border-slate-200 text-sm"
                        placeholder="Plan Impulso Individual"
                    />
                </Field>
                <Field label="Slug" error={errors.slug}>
                    <input
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value)}
                        className="w-full rounded-lg border-slate-200 font-mono text-sm"
                        placeholder="plan-impulso-individual"
                    />
                </Field>
            </div>

            <Field label="Descripción" error={errors.description}>
                <textarea
                    value={data.description || ''}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={3}
                    className="w-full rounded-lg border-slate-200 text-sm"
                />
            </Field>

            <div className="grid gap-4 md:grid-cols-3">
                <Field label="Tipo" error={errors.type}>
                    <select
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        className="w-full rounded-lg border-slate-200 text-sm"
                    >
                        <option value="individual">Individual</option>
                        <option value="group">Grupal (CombiMindMeet)</option>
                    </select>
                </Field>
                <Field label="Precio (MXN)" error={errors.price}>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                        className="w-full rounded-lg border-slate-200 text-sm"
                        placeholder="1490.00"
                    />
                </Field>
                <Field label="Max slots" error={errors.max_slots}>
                    <input
                        type="number"
                        min="2"
                        value={data.max_slots}
                        onChange={(e) => setData('max_slots', e.target.value)}
                        disabled={!isGroup}
                        className="w-full rounded-lg border-slate-200 text-sm disabled:bg-slate-50 disabled:text-slate-400"
                        placeholder={isGroup ? '5' : 'Solo para grupales'}
                    />
                </Field>
            </div>

            <Field label="Stripe Product ID" error={errors.stripe_product_id}>
                <input
                    value={data.stripe_product_id || ''}
                    onChange={(e) => setData('stripe_product_id', e.target.value)}
                    className="w-full rounded-lg border-slate-200 font-mono text-sm"
                    placeholder="prod_XXXXXXXXXXXXXXXX"
                />
            </Field>

            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={() => setData('is_active', !data.is_active)}
                    className="rounded border-slate-300"
                />
                Paquete activo (visible para psicólogos)
            </label>

            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onClick={onClose} className="rounded-lg px-4 py-2 text-sm text-slate-600">
                    Cancelar
                </button>
                <PrimaryButton disabled={processing}>
                    {processing ? 'Guardando...' : 'Guardar paquete'}
                </PrimaryButton>
            </div>
        </form>
    );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-1 block text-xs font-semibold text-slate-600">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
