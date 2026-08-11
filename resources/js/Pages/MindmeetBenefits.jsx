import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

const emptyBenefit = {
    title: '',
    partner_name: '',
    category: '',
    description: '',
    terms: '',
    coupon_code: '',
    image_url: '',
    image: null,
    redirect_url: '',
    contact_label: '',
    contact_url: '',
    starts_at: '',
    ends_at: '',
    sort_order: 0,
    is_active: true,
};

export default function MindmeetBenefits({ auth, benefits = [], categories = [] }) {
    const [showModal, setShowModal] = useState(false);
    const [editingBenefit, setEditingBenefit] = useState(null);

    const stats = useMemo(() => ({
        total: benefits.length,
        active: benefits.filter((benefit) => benefit.is_available).length,
        inactive: benefits.filter((benefit) => !benefit.is_available).length,
        categories: new Set(benefits.map((benefit) => benefit.category).filter(Boolean)).size,
    }), [benefits]);

    const openCreate = () => {
        setEditingBenefit(null);
        setShowModal(true);
    };

    const openEdit = (benefit) => {
        setEditingBenefit(benefit);
        setShowModal(true);
    };

    const deleteBenefit = (benefit) => {
        if (!window.confirm(`Eliminar el beneficio "${benefit.title}"?`)) return;

        router.delete(route('mindmeet-benefits.destroy', benefit.id), {
            preserveScroll: true,
        });
    };

    const columns = [
        {
            name: 'Beneficio',
            selector: row => row.title,
            sortable: true,
            grow: 2,
            cell: row => (
                <button type="button" onClick={() => openEdit(row)} className="flex items-center gap-3 text-left">
                    {row.image_url ? (
                        <img src={row.image_url} alt={row.title} className="h-14 w-20 rounded-xl object-cover" />
                    ) : (
                        <div className="flex h-14 w-20 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-blue-700">
                            Sin imagen
                        </div>
                    )}
                    <span>
                        <span className="block font-bold text-slate-950">{row.title}</span>
                        <span className="block text-xs text-slate-500">{row.partner_name || 'Sin aliado'}</span>
                    </span>
                </button>
            ),
        },
        {
            name: 'Categoria',
            selector: row => row.category || '',
            cell: row => row.category ? (
                <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{row.category}</span>
            ) : <span className="text-xs text-slate-400">Sin categoria</span>,
        },
        {
            name: 'Codigo',
            selector: row => row.coupon_code || '',
            cell: row => row.coupon_code ? (
                <span className="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{row.coupon_code}</span>
            ) : <span className="text-xs text-slate-400">No aplica</span>,
        },
        {
            name: 'Vigencia',
            selector: row => row.ends_at || '',
            grow: 1.4,
            cell: row => (
                <span className="text-xs text-slate-600">
                    {row.starts_at ? row.starts_at.replace('T', ' ') : 'Ahora'} - {row.ends_at ? row.ends_at.replace('T', ' ') : 'Sin fin'}
                </span>
            ),
        },
        {
            name: 'Orden',
            selector: row => Number(row.sort_order || 0),
            sortable: true,
            cell: row => <span className="text-sm font-semibold text-slate-700">{row.sort_order}</span>,
        },
        {
            name: 'Estado',
            cell: row => (
                <span className={`rounded-full px-3 py-1 text-xs font-bold ${row.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                    {row.is_available ? 'Visible' : 'Oculto'}
                </span>
            ),
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex gap-3">
                    <button type="button" onClick={() => openEdit(row)} className="text-blue-700 hover:underline">Editar</button>
                    <button type="button" onClick={() => deleteBenefit(row)} className="text-red-600 hover:underline">Eliminar</button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Beneficios MindMeet</h2>}
        >
            <Head title="Beneficios MindMeet" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-blue-700">Membership care</p>
                                <h1 className="text-2xl font-black text-slate-950">Beneficios para miembros MindMeet</h1>
                                <p className="mt-1 max-w-2xl text-sm text-slate-600">
                                    Administra descuentos, codigos y contactos exclusivos para psicologos con membresia activa.
                                </p>
                            </div>
                            <PrimaryButton onClick={openCreate}>Nuevo beneficio</PrimaryButton>
                        </div>

                        <div className="mt-6 grid gap-3 md:grid-cols-4">
                            <Metric label="Total" value={stats.total} />
                            <Metric label="Visibles" value={stats.active} tone="emerald" />
                            <Metric label="Ocultos" value={stats.inactive} tone="slate" />
                            <Metric label="Categorias" value={stats.categories} tone="blue" />
                        </div>
                    </section>

                    <section className="rounded-2xl bg-white p-4 shadow-sm">
                        <DataTable
                            columns={columns}
                            data={benefits}
                            pagination
                            paginationPerPage={12}
                            persistTableHead
                            noDataComponent="No hay beneficios creados."
                        />
                    </section>
                </div>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="3xl">
                <BenefitForm
                    benefit={editingBenefit}
                    categories={categories}
                    onClose={() => setShowModal(false)}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value, tone = 'blue' }) {
    const tones = {
        blue: 'bg-blue-50 text-blue-700 border-blue-100',
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        slate: 'bg-slate-50 text-slate-700 border-slate-100',
    };

    return (
        <div className={`rounded-2xl border p-4 ${tones[tone]}`}>
            <p className="text-xs font-bold uppercase tracking-[0.18em] opacity-80">{label}</p>
            <p className="mt-2 text-3xl font-black">{value}</p>
        </div>
    );
}

function BenefitForm({ benefit, categories, onClose }) {
    const { data, setData, processing, errors, reset } = useForm({
        ...emptyBenefit,
        ...benefit,
        image: null,
        sort_order: benefit?.sort_order ?? 0,
        is_active: benefit?.is_active ?? true,
    });

    const submit = (event) => {
        event.preventDefault();

        const payload = {
            ...data,
            _method: benefit?.id ? 'put' : 'post',
        };

        const url = benefit?.id
            ? route('mindmeet-benefits.update', benefit.id)
            : route('mindmeet-benefits.store');

        router.post(url, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5 p-6">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">
                    {benefit?.id ? 'Editar beneficio' : 'Nuevo beneficio'}
                </p>
                <h2 className="text-xl font-bold text-slate-950">Configuracion del beneficio</h2>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Titulo" error={errors.title}>
                    <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="10% de descuento en Maskota" />
                </Field>
                <Field label="Aliado / marca" error={errors.partner_name}>
                    <input value={data.partner_name || ''} onChange={(event) => setData('partner_name', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Maskota" />
                </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Field label="Categoria" error={errors.category}>
                    <input
                        value={data.category || ''}
                        onChange={(event) => setData('category', event.target.value)}
                        list="benefit-categories"
                        className="w-full rounded-lg border-slate-200 text-sm"
                        placeholder="Mascotas"
                    />
                    <datalist id="benefit-categories">
                        {categories?.map((category) => <option key={category} value={category} />)}
                    </datalist>
                </Field>
                <Field label="Codigo" error={errors.coupon_code}>
                    <input value={data.coupon_code || ''} onChange={(event) => setData('coupon_code', event.target.value.toUpperCase())} className="w-full rounded-lg border-slate-200 text-sm" placeholder="MINDKOTA10" />
                </Field>
                <Field label="Orden" error={errors.sort_order}>
                    <input type="number" min="0" value={data.sort_order || 0} onChange={(event) => setData('sort_order', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
            </div>

            <Field label="Descripcion corta" error={errors.description}>
                <textarea value={data.description || ''} onChange={(event) => setData('description', event.target.value)} rows={3} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Beneficio exclusivo para miembros activos de MindMeet." />
            </Field>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="URL de imagen" error={errors.image_url}>
                    <input type="url" value={data.image_url || ''} onChange={(event) => setData('image_url', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="https://..." />
                </Field>
                <Field label="Subir imagen" error={errors.image}>
                    <input type="file" accept="image/*" onChange={(event) => setData('image', event.target.files?.[0] || null)} className="w-full rounded-lg border border-slate-200 p-2 text-sm" />
                </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Link destino" error={errors.redirect_url}>
                    <input type="url" value={data.redirect_url || ''} onChange={(event) => setData('redirect_url', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="https://..." />
                </Field>
                <Field label="Contacto" error={errors.contact_url}>
                    <div className="grid gap-2 md:grid-cols-[0.8fr_1.2fr]">
                        <input value={data.contact_label || ''} onChange={(event) => setData('contact_label', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="WhatsApp" />
                        <input value={data.contact_url || ''} onChange={(event) => setData('contact_url', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="https://wa.me/... o telefono" />
                    </div>
                </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Inicia" error={errors.starts_at}>
                    <input type="datetime-local" value={data.starts_at || ''} onChange={(event) => setData('starts_at', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Termina" error={errors.ends_at}>
                    <input type="datetime-local" value={data.ends_at || ''} onChange={(event) => setData('ends_at', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
            </div>

            <Field label="Terminos y condiciones" error={errors.terms}>
                <textarea value={data.terms || ''} onChange={(event) => setData('terms', event.target.value)} rows={3} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Valido en tiendas fisicas y en linea. No acumulable..." />
            </Field>

            <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" checked={Boolean(data.is_active)} onChange={(event) => setData('is_active', event.target.checked)} className="rounded border-slate-300 text-blue-600" />
                Beneficio activo
            </label>

            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onClick={onClose} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">
                    Cancelar
                </button>
                <PrimaryButton disabled={processing}>{benefit?.id ? 'Guardar cambios' : 'Crear beneficio'}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-slate-500">{label}</span>
            {children}
            {error ? <span className="mt-1 block text-xs font-semibold text-red-600">{error}</span> : null}
        </label>
    );
}
