import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

const emptyForm = {
    is_enabled: true,
    custom_title: '',
    custom_description: '',
    custom_price: '',
    custom_currency: 'MXN',
    custom_therapy_type: '',
    custom_certification: '',
    custom_image_url: '',
    custom_public_url: '',
    custom_schedule_summary: '',
    custom_availability: '',
};

const feedStatusStyles = {
    published: 'bg-emerald-100 text-emerald-700',
    draft: 'bg-amber-100 text-amber-700',
};

export default function FacebookCatalog({ auth, entries = [], summary = {}, feedUrl }) {
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [search, setSearch] = useState('');

    const filteredEntries = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return entries;

        return entries.filter((entry) =>
            entry.psychologist.name?.toLowerCase().includes(term)
            || entry.psychologist.email?.toLowerCase().includes(term)
            || entry.effective.therapy_type?.toLowerCase().includes(term)
        );
    }, [entries, search]);

    const columns = [
        {
            name: 'Psicologo',
            grow: 2,
            sortable: true,
            selector: row => row.psychologist.name,
            cell: row => (
                <button
                    type="button"
                    onClick={() => setSelectedEntry(row)}
                    className="flex items-center gap-3 py-3 text-left"
                >
                    <FotoPerfil image={row.psychologist.image || null} name={row.psychologist.name} className="h-11 w-11 rounded-full" alt={row.psychologist.name} />
                    <div>
                        <span className="block font-semibold text-slate-900">{row.psychologist.name}</span>
                        <span className="text-xs text-slate-500">{row.psychologist.email}</span>
                    </div>
                </button>
            ),
        },
        {
            name: 'Feed',
            selector: row => row.feed_status,
            cell: row => (
                <div className="space-y-1">
                    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${feedStatusStyles[row.feed_status] || feedStatusStyles.draft}`}>
                        {row.feed_status === 'published' ? 'Publicado' : 'Borrador'}
                    </span>
                    <p className="text-xs text-slate-500">
                        {row.overrides.is_enabled ? 'Incluido' : 'Excluido'}
                    </p>
                </div>
            ),
        },
        {
            name: 'Tipo',
            selector: row => row.effective.therapy_type,
            cell: row => <span className="text-sm text-slate-700">{row.effective.therapy_type || 'Sin tipo'}</span>,
        },
        {
            name: 'Precio',
            selector: row => Number(row.effective.price || 0),
            sortable: true,
            cell: row => (
                <span className="font-semibold text-slate-900">
                    {row.effective.price !== null && row.effective.price !== undefined
                        ? `$${Number(row.effective.price).toFixed(2)} ${row.effective.currency}`
                        : 'Sin precio'}
                </span>
            ),
        },
        {
            name: 'Certificacion',
            grow: 1.6,
            selector: row => row.effective.certification,
            cell: row => <span className="text-sm text-slate-600">{row.effective.certification}</span>,
        },
        {
            name: 'Horario',
            grow: 1.6,
            selector: row => row.effective.schedule_summary,
            cell: row => <span className="text-sm text-slate-600">{row.effective.schedule_summary}</span>,
        },
        {
            name: 'Link publico',
            grow: 1.8,
            selector: row => row.public_url,
            cell: row => row.public_url ? (
                <a
                    href={row.public_url}
                    target="_blank"
                    rel="noreferrer"
                    className="max-w-[240px] truncate text-sm font-medium text-blue-700 hover:text-blue-800"
                    title={row.public_url}
                >
                    {row.public_url}
                </a>
            ) : (
                <span className="text-sm text-slate-400">Sin enlace</span>
            ),
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex gap-3">
                    <button type="button" onClick={() => setSelectedEntry(row)} className="text-sm font-semibold text-blue-700 hover:text-blue-800">
                        Editar
                    </button>
                    <a href={row.public_url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-slate-600 hover:text-slate-800">
                        Ver publico
                    </a>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Catalogo Facebook</h2>}
        >
            <Head title="Catalogo Facebook" />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 p-6 text-white">
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">Meta commerce</p>
                            <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h1 className="text-3xl font-black tracking-tight">Catalogo de psicologos para Facebook</h1>
                                    <p className="mt-2 max-w-3xl text-sm text-blue-100">
                                        Administra la foto, descripcion corta, terapia, precio, certificacion, enlace publico y resumen de horarios
                                        que se exportan al feed del catalogo.
                                    </p>
                                </div>
                                <a
                                    href={feedUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20"
                                >
                                    Abrir feed CSV
                                </a>
                            </div>
                        </div>

                        <div className="grid gap-4 border-b border-slate-100 p-5 md:grid-cols-4">
                            <Kpi title="Psicologos totales" value={summary.total} />
                            <Kpi title="Listos para catalogo" value={summary.ready} />
                            <Kpi title="Publicados en feed" value={summary.published} />
                            <Kpi title="Con ajustes manuales" value={summary.with_overrides} />
                        </div>

                        <div className="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">Vista de catalogo</h2>
                                <p className="text-sm text-slate-500">
                                    Si un perfil no esta listo o no es visible publicamente, se queda como borrador aunque tenga datos.
                                </p>
                            </div>
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Buscar psicologo o terapia"
                                className="rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>

                        <div className="p-4 pt-0">
                            <DataTable
                                columns={columns}
                                data={filteredEntries}
                                pagination
                                paginationPerPage={12}
                                persistTableHead
                                responsive
                                noDataComponent="No hay psicologos para mostrar."
                            />
                        </div>
                    </section>
                </div>
            </div>

            <Modal show={Boolean(selectedEntry)} onClose={() => setSelectedEntry(null)} maxWidth="4xl">
                {selectedEntry ? (
                    <CatalogEditor entry={selectedEntry} onClose={() => setSelectedEntry(null)} />
                ) : null}
            </Modal>
        </AuthenticatedLayout>
    );
}

function CatalogEditor({ entry, onClose }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        ...emptyForm,
        ...entry.overrides,
        is_enabled: entry.overrides?.is_enabled ?? true,
        custom_title: entry.overrides?.custom_title || '',
        custom_description: entry.overrides?.custom_description || '',
        custom_price: entry.overrides?.custom_price || '',
        custom_currency: entry.overrides?.custom_currency || 'MXN',
        custom_therapy_type: entry.overrides?.custom_therapy_type || '',
        custom_certification: entry.overrides?.custom_certification || '',
        custom_image_url: entry.overrides?.custom_image_url || '',
        custom_public_url: entry.overrides?.custom_public_url || '',
        custom_schedule_summary: entry.overrides?.custom_schedule_summary || '',
        custom_availability: entry.overrides?.custom_availability || '',
    });

    const submit = (event) => {
        event.preventDefault();

        put(route('facebook-catalog.upsert', entry.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const useDefaults = () => {
        reset();
        setData({
            ...emptyForm,
            is_enabled: true,
            custom_currency: 'MXN',
        });
    };

    return (
        <form onSubmit={submit} className="max-h-[88vh] overflow-y-auto">
            <div className="border-b border-slate-100 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="flex items-center gap-4">
                        <FotoPerfil image={entry.psychologist.image || null} name={entry.psychologist.name} className="h-14 w-14 rounded-full" alt={entry.psychologist.name} />
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Editor de item</p>
                            <h2 className="text-2xl font-black text-slate-950">{entry.psychologist.name}</h2>
                            <p className="text-sm text-slate-500">{entry.psychologist.email}</p>
                        </div>
                    </div>
                    <div className="space-y-2 text-sm">
                        <span className={`inline-flex rounded-full px-3 py-1 font-semibold ${feedStatusStyles[entry.feed_status] || feedStatusStyles.draft}`}>
                            {entry.feed_status === 'published' ? 'Publicado en feed' : 'Borrador'}
                        </span>
                        <p className="text-slate-500">{entry.catalog_ready ? 'Con datos completos para Meta.' : 'Faltan datos esenciales para publicarlo.'}</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 p-6 lg:grid-cols-[1.3fr_0.9fr]">
                <div className="space-y-5">
                    <label className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.is_enabled}
                            onChange={() => setData('is_enabled', !data.is_enabled)}
                            className="rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                        />
                        Incluir psicologo en el catalogo de Facebook
                    </label>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Titulo comercial" error={errors.custom_title} hint={`Default: ${entry.defaults.title}`}>
                            <input value={data.custom_title} onChange={(event) => setData('custom_title', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.title} />
                        </Field>
                        <Field label="Tipo de terapia" error={errors.custom_therapy_type} hint={`Default: ${entry.defaults.therapy_type}`}>
                            <input value={data.custom_therapy_type} onChange={(event) => setData('custom_therapy_type', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.therapy_type} />
                        </Field>
                    </div>

                    <Field label="Descripcion corta" error={errors.custom_description} hint="Ideal para little description del anuncio o feed.">
                        <textarea value={data.custom_description} onChange={(event) => setData('custom_description', event.target.value)} rows={4} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.description} />
                    </Field>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Precio" error={errors.custom_price} hint={`Default: ${entry.defaults.price ? `$${Number(entry.defaults.price).toFixed(2)}` : 'Sin precio'}`}>
                            <input type="number" min="0" step="0.01" value={data.custom_price} onChange={(event) => setData('custom_price', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.price || ''} />
                        </Field>
                        <Field label="Moneda" error={errors.custom_currency}>
                            <input value={data.custom_currency} onChange={(event) => setData('custom_currency', event.target.value.toUpperCase())} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                        </Field>
                        <Field label="Disponibilidad" error={errors.custom_availability} hint={`Default: ${entry.defaults.availability}`}>
                            <select value={data.custom_availability} onChange={(event) => setData('custom_availability', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm">
                                <option value="">Usar default</option>
                                <option value="in stock">in stock</option>
                                <option value="available for order">available for order</option>
                                <option value="preorder">preorder</option>
                                <option value="out of stock">out of stock</option>
                            </select>
                        </Field>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Certificacion" error={errors.custom_certification} hint={`Default: ${entry.defaults.certification}`}>
                            <input value={data.custom_certification} onChange={(event) => setData('custom_certification', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.certification} />
                        </Field>
                        <Field label="Horarios" error={errors.custom_schedule_summary} hint={`Default: ${entry.defaults.schedule_summary}`}>
                            <input value={data.custom_schedule_summary} onChange={(event) => setData('custom_schedule_summary', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.schedule_summary} />
                        </Field>
                    </div>

                    <div className="grid gap-4">
                        <Field label="Imagen" error={errors.custom_image_url} hint="Dejalo vacio para usar la foto del perfil del psicologo.">
                            <input value={data.custom_image_url} onChange={(event) => setData('custom_image_url', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.image_url} />
                        </Field>
                        <Field label="Enlace publico" error={errors.custom_public_url} hint="Puedes mandar al perfil publico o a una landing dedicada.">
                            <input value={data.custom_public_url} onChange={(event) => setData('custom_public_url', event.target.value)} className="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder={entry.defaults.public_url} />
                        </Field>
                    </div>
                </div>

                <aside className="space-y-4">
                    <PreviewCard entry={entry} data={data} />
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <p className="font-bold text-slate-900">Notas del modulo</p>
                        <ul className="mt-3 space-y-2">
                            <li>La foto, el precio y el link salen del perfil publico si no agregas override.</li>
                            <li>Solo los psicologos visibles publicamente pueden salir como publicados en el feed.</li>
                            <li>La certificacion toma primero la cedula profesional validada si existe.</li>
                        </ul>
                    </div>
                </aside>
            </div>

            <div className="flex items-center justify-between border-t border-slate-100 px-6 py-4">
                <button type="button" onClick={useDefaults} className="text-sm font-semibold text-slate-500 hover:text-slate-700">
                    Limpiar overrides
                </button>
                <div className="flex gap-3">
                    <button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600">
                        Cerrar
                    </button>
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar item'}
                    </PrimaryButton>
                </div>
            </div>
        </form>
    );
}

function PreviewCard({ entry, data }) {
    const preview = {
        title: data.custom_title || entry.defaults.title,
        description: data.custom_description || entry.defaults.description,
        price: data.custom_price || entry.defaults.price,
        currency: data.custom_currency || entry.defaults.currency,
        therapy_type: data.custom_therapy_type || entry.defaults.therapy_type,
        certification: data.custom_certification || entry.defaults.certification,
        image_url: data.custom_image_url || entry.defaults.image_url,
        public_url: data.custom_public_url || entry.defaults.public_url,
        schedule_summary: data.custom_schedule_summary || entry.defaults.schedule_summary,
    };

    return (
        <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 p-4">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-blue-700">Preview del feed</p>
                <h3 className="mt-2 text-lg font-black text-slate-950">{preview.title}</h3>
            </div>
            <div className="space-y-4 p-4">
                {preview.image_url ? (
                    <img src={preview.image_url} alt={preview.title} className="h-48 w-full rounded-2xl object-cover" />
                ) : (
                    <div className="flex h-48 items-center justify-center rounded-2xl bg-slate-100 text-sm text-slate-500">Sin imagen</div>
                )}
                <div className="space-y-2 text-sm text-slate-600">
                    <p>{preview.description}</p>
                    <p><span className="font-semibold text-slate-900">Terapia:</span> {preview.therapy_type}</p>
                    <p><span className="font-semibold text-slate-900">Precio:</span> {preview.price ? `$${Number(preview.price).toFixed(2)} ${preview.currency}` : 'Sin precio'}</p>
                    <p><span className="font-semibold text-slate-900">Certificacion:</span> {preview.certification}</p>
                    <p><span className="font-semibold text-slate-900">Horarios:</span> {preview.schedule_summary}</p>
                    <a href={preview.public_url} target="_blank" rel="noreferrer" className="inline-flex text-blue-700 hover:text-blue-800">
                        Abrir enlace publico
                    </a>
                </div>
            </div>
        </div>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <label className="block text-sm font-semibold text-slate-700">
            <span className="mb-1 block">{label}</span>
            {children}
            {hint ? <span className="mt-1 block text-xs font-normal text-slate-500">{hint}</span> : null}
            {error ? <span className="mt-1 block text-xs font-semibold text-red-600">{error}</span> : null}
        </label>
    );
}

function Kpi({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{title}</p>
            <p className="mt-2 text-2xl font-black text-slate-950">{value || 0}</p>
        </div>
    );
}
