import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ClipboardDocumentIcon,
    EyeIcon,
    EyeSlashIcon,
    MagnifyingGlassIcon,
    QrCodeIcon,
    UserPlusIcon,
} from '@heroicons/react/24/outline';
import { useMemo, useState } from 'react';
import DeleteVendedorModal from './Vendedores/DeleteVendedorModal';

const FILTERS = [
    { key: 'all', label: 'Todos' },
    { key: 'active', label: 'Activos' },
    { key: 'pending', label: 'Comision pendiente' },
    { key: 'inactive', label: 'Inactivos' },
];

const ROLE_LABELS = {
    vendedor: 'Vendedor',
    supervisor: 'Supervisor',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0));
}

function imageUrl(path) {
    if (!path) {
        return null;
    }

    if (path.startsWith('http')) {
        return path;
    }

    return `/storage/${path}`;
}

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || 'V';
}

function StatusBadge({ status }) {
    const active = status === 'active';

    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'}`}>
            {active ? 'Activo' : 'Inactivo'}
        </span>
    );
}

function MetricCard({ label, value, help }) {
    return (
        <div className="rounded-lg border border-sky-100 bg-white p-5 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-slate-500">{label}</p>
            <p className="mt-3 text-3xl font-black text-slate-950">{value}</p>
            {help && <p className="mt-1 text-sm text-slate-500">{help}</p>}
        </div>
    );
}

function MiniStat({ label, value }) {
    return (
        <div className="rounded-lg bg-sky-50 px-3 py-2">
            <p className="text-lg font-black text-sky-800">{value}</p>
            <p className="text-xs font-semibold text-sky-700">{label}</p>
        </div>
    );
}

function CopyButton({ value }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 1600);
    };

    return (
        <button
            type="button"
            onClick={copy}
            className="inline-flex items-center justify-center gap-2 rounded-md bg-gradient-to-r from-teal-500 to-sky-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:from-teal-600 hover:to-sky-800"
        >
            <ClipboardDocumentIcon className="h-4 w-4" />
            {copied ? 'Copiado' : 'Copiar link'}
        </button>
    );
}

function SellerAvatar({ vendedor }) {
    const src = imageUrl(vendedor.imagen);

    return (
        <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-sky-100 bg-gradient-to-br from-sky-100 to-teal-50 text-sm font-black text-sky-800">
            {src ? (
                <img src={src} alt={vendedor.nombre} className="h-full w-full object-cover" />
            ) : (
                initials(vendedor.nombre)
            )}
        </div>
    );
}

export default function Vendedores({ auth, vendedores = [] }) {
    const { flash } = usePage().props;
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [selectedVendedor, setSelectedVendedor] = useState(null);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');

    const stats = useMemo(() => {
        const active = vendedores.filter((seller) => seller.status === 'active').length;
        const pendingAmount = vendedores.reduce((sum, seller) => sum + Number(seller.pending_commissions_sum || 0), 0);
        const referrals = vendedores.reduce((sum, seller) => sum + Number(seller.referrals_count || 0), 0);
        const paid = vendedores.reduce((sum, seller) => sum + Number(seller.active_referrals_count || 0), 0);

        return { active, pendingAmount, referrals, paid };
    }, [vendedores]);

    const filteredVendedores = useMemo(() => {
        const term = search.trim().toLowerCase();

        return vendedores.filter((seller) => {
            const matchesSearch = !term || [
                seller.nombre,
                seller.email,
                seller.telefono,
                seller.ciudad,
                seller.estado,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            const matchesFilter =
                filter === 'all'
                || (filter === 'active' && seller.status === 'active')
                || (filter === 'inactive' && seller.status !== 'active')
                || (filter === 'pending' && Number(seller.pending_commissions_sum || 0) > 0);

            return matchesSearch && matchesFilter;
        });
    }, [filter, search, vendedores]);

    const openCreateModal = () => {
        setSelectedVendedor(null);
        setShowModal(true);
    };

    const openEditModal = (vendedor) => {
        setSelectedVendedor(vendedor);
        setShowModal(true);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Vendedores</h2>}
        >
            <Head title="Vendedores" />

            <div className="bg-slate-100 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                            {flash.success}
                        </div>
                    )}

                    <section className="rounded-lg border border-sky-100 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.35em] text-sky-700">Growth</p>
                                <h1 className="mt-2 text-3xl font-black text-slate-950">Red de vendedores MindMeet</h1>
                                <p className="mt-2 max-w-3xl text-sm text-slate-500">
                                    Administra vendedores, QR de registro, referidos y comisiones sin perder de vista quien ya genero pagos.
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={route('seller-commissions')}
                                    className="inline-flex items-center justify-center rounded-md border border-sky-200 bg-white px-4 py-2 text-sm font-bold text-sky-800 transition hover:bg-sky-50"
                                >
                                    Ver pagos
                                </Link>
                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    className="inline-flex items-center justify-center gap-2 rounded-md bg-sky-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-sky-800"
                                >
                                    <UserPlusIcon className="h-4 w-4" />
                                    Agregar vendedor
                                </button>
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-4 md:grid-cols-4">
                        <MetricCard label="Vendedores" value={vendedores.length} help={`${stats.active} activos`} />
                        <MetricCard label="Referidos" value={stats.referrals} help={`${stats.paid} con membresia activa`} />
                        <MetricCard label="Por pagar" value={money(stats.pendingAmount)} help="Comisiones pendientes" />
                        <MetricCard label="Conversion" value={stats.referrals ? `${Math.round((stats.paid / stats.referrals) * 100)}%` : '0%'} help="Referidos con pago" />
                    </section>

                    <section className="rounded-lg border border-sky-100 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[0.3em] text-sky-700">Seguimiento</p>
                                    <h2 className="mt-1 text-xl font-black text-slate-950">Vendedores registrados</h2>
                                    <p className="text-sm text-slate-500">{filteredVendedores.length} de {vendedores.length} vendedores en esta vista.</p>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div className="relative">
                                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            className="w-full rounded-lg border border-sky-100 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100 sm:w-80"
                                            placeholder="Buscar nombre, correo, telefono o estado"
                                        />
                                    </div>

                                    <div className="flex rounded-lg border border-sky-100 bg-slate-50 p-1">
                                        {FILTERS.map((item) => (
                                            <button
                                                key={item.key}
                                                type="button"
                                                onClick={() => setFilter(item.key)}
                                                className={`rounded-md px-3 py-2 text-xs font-bold transition ${filter === item.key ? 'bg-sky-700 text-white shadow-sm' : 'text-slate-600 hover:bg-white'}`}
                                            >
                                                {item.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="divide-y divide-slate-100">
                            {filteredVendedores.length === 0 ? (
                                <div className="p-10 text-center text-sm text-slate-500">
                                    No hay vendedores con estos filtros.
                                </div>
                            ) : (
                                filteredVendedores.map((vendedor) => (
                                    <article key={vendedor.id} className="p-5 transition hover:bg-sky-50/40">
                                        <div className="grid gap-5 xl:grid-cols-[minmax(260px,1fr)_240px_minmax(320px,1.2fr)_180px] xl:items-center">
                                            <div className="flex gap-4">
                                                <SellerAvatar vendedor={vendedor} />
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h3 className="font-black text-slate-950">{vendedor.nombre}</h3>
                                                        <StatusBadge status={vendedor.status} />
                                                    </div>
                                                    <p className="truncate text-sm text-slate-500">{vendedor.email}</p>
                                                    <p className="text-sm font-semibold text-sky-800">{vendedor.telefono}</p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        {ROLE_LABELS[vendedor.rol] || vendedor.rol} · {vendedor.ciudad || 'Sin ciudad'} {vendedor.estado ? `, ${vendedor.estado}` : ''}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-3 gap-2">
                                                <MiniStat label="Referidos" value={vendedor.referrals_count || 0} />
                                                <MiniStat label="Activos" value={vendedor.active_referrals_count || 0} />
                                                <MiniStat label="Sin pago" value={vendedor.unpaid_referrals_count || 0} />
                                            </div>

                                            <div className="rounded-lg border border-sky-100 bg-sky-50/70 p-3">
                                                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-sky-700">
                                                    <QrCodeIcon className="h-4 w-4" />
                                                    Link de registro
                                                </div>
                                                <p className="mt-2 truncate rounded-md bg-white px-3 py-2 text-xs font-semibold text-sky-900">
                                                    {vendedor.registration_url}
                                                </p>
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    <CopyButton value={vendedor.registration_url} />
                                                    <a
                                                        href={route('vendedores.qr.download', vendedor.id)}
                                                        className="inline-flex items-center justify-center rounded-md border border-sky-200 bg-white px-4 py-2 text-sm font-bold text-sky-800 transition hover:bg-sky-50"
                                                    >
                                                        Descargar QR
                                                    </a>
                                                </div>
                                            </div>

                                            <div className="flex flex-col gap-2 xl:items-end">
                                                <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
                                                    {money(vendedor.pending_commissions_sum)} pendiente
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => openEditModal(vendedor)}
                                                    className="w-full rounded-md border border-sky-200 bg-white px-4 py-2 text-sm font-bold text-sky-800 transition hover:bg-sky-50 xl:w-auto"
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedVendedor(vendedor);
                                                        setShowDeleteModal(true);
                                                    }}
                                                    className="w-full rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-50 xl:w-auto"
                                                >
                                                    Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                ))
                            )}
                        </div>
                    </section>
                </div>
            </div>

            <Modal
                show={showModal}
                onClose={() => {
                    setShowModal(false);
                    setSelectedVendedor(null);
                }}
                maxWidth="2xl"
            >
                <FormCreateEditVendedor
                    key={selectedVendedor?.id ?? 'create'}
                    vendedor={selectedVendedor}
                    onClose={() => {
                        setShowModal(false);
                        setSelectedVendedor(null);
                    }}
                />
            </Modal>

            <Modal show={showDeleteModal} onClose={() => setShowDeleteModal(false)}>
                {selectedVendedor && (
                    <DeleteVendedorModal
                        vendedor={selectedVendedor}
                        onClose={() => {
                            setShowDeleteModal(false);
                            setSelectedVendedor(null);
                        }}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-bold text-slate-700">{label}</span>
            <div className="mt-1">{children}</div>
            <InputError message={error} className="mt-1" />
        </label>
    );
}

function FormCreateEditVendedor({ vendedor = null, onClose }) {
    const isEdit = Boolean(vendedor);
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        _token: csrfToken(),
        _method: isEdit ? 'put' : 'post',
        nombre: vendedor?.nombre || '',
        email: vendedor?.email || '',
        telefono: vendedor?.telefono || '',
        password: '',
        password_confirmation: '',
        direccion: vendedor?.direccion || '',
        ciudad: vendedor?.ciudad || '',
        estado: vendedor?.estado || '',
        codigo_postal: vendedor?.codigo_postal || '',
        pais: vendedor?.pais || 'Mexico',
        rol: vendedor?.rol || 'vendedor',
        status: vendedor?.status || 'active',
        imagen: null,
    });

    const handleChange = (event) => {
        const { name, value, type, files } = event.target;
        setData(name, type === 'file' ? files[0] : value);
    };

    const submit = (event) => {
        event.preventDefault();

        const targetRoute = isEdit
            ? route('vendedores.update', vendedor.id)
            : route('vendedores.store');

        post(targetRoute, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose?.();
            },
        });
    };

    const inputClass = 'w-full rounded-lg border border-sky-100 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100';

    return (
        <form onSubmit={submit} className="max-h-[85vh] overflow-y-auto">
            <div className="border-b border-slate-100 p-6">
                <p className="text-xs font-bold uppercase tracking-[0.28em] text-sky-700">
                    {isEdit ? 'Editar' : 'Nuevo'}
                </p>
                <h2 className="mt-1 text-2xl font-black text-slate-950">
                    {isEdit ? 'Editar vendedor' : 'Agregar vendedor'}
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                    Crea su acceso, configura su estatus y genera automaticamente su link/QR de registro.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
                <Field label="Nombre completo" error={errors.nombre}>
                    <input name="nombre" value={data.nombre} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Correo electronico" error={errors.email}>
                    <input name="email" type="email" value={data.email} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Telefono" error={errors.telefono}>
                    <input name="telefono" value={data.telefono} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Rol" error={errors.rol}>
                    <select name="rol" value={data.rol} onChange={handleChange} className={inputClass}>
                        <option value="vendedor">Vendedor</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </Field>

                <Field label="Estatus" error={errors.status}>
                    <select name="status" value={data.status} onChange={handleChange} className={inputClass}>
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </Field>

                <Field label="Pais" error={errors.pais}>
                    <input name="pais" value={data.pais} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label={`Password ${isEdit ? '(opcional)' : ''}`} error={errors.password}>
                    <div className="relative">
                        <input
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            onChange={handleChange}
                            className={`${inputClass} pr-11`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((value) => !value)}
                            className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-sky-700"
                        >
                            {showPassword ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                        </button>
                    </div>
                </Field>

                <Field label="Confirmar password" error={errors.password_confirmation}>
                    <input
                        name="password_confirmation"
                        type={showPassword ? 'text' : 'password'}
                        value={data.password_confirmation}
                        onChange={handleChange}
                        className={inputClass}
                    />
                </Field>

                <Field label="Direccion" error={errors.direccion}>
                    <input name="direccion" value={data.direccion} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Ciudad" error={errors.ciudad}>
                    <input name="ciudad" value={data.ciudad} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Estado" error={errors.estado}>
                    <input name="estado" value={data.estado} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Codigo postal" error={errors.codigo_postal}>
                    <input name="codigo_postal" value={data.codigo_postal} onChange={handleChange} className={inputClass} />
                </Field>

                <Field label="Foto del vendedor" error={errors.imagen} className="md:col-span-2">
                    <input
                        name="imagen"
                        type="file"
                        accept="image/*"
                        onChange={handleChange}
                        className="w-full rounded-lg border border-dashed border-sky-200 bg-sky-50/60 px-3 py-3 text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-sky-700 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                    />
                </Field>
            </div>

            <div className="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-sky-700 px-5 py-2 text-sm font-bold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing ? 'Guardando...' : isEdit ? 'Guardar cambios' : 'Crear vendedor'}
                </button>
            </div>
        </form>
    );
}
