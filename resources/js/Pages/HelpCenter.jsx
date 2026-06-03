import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const emptyArticle = {
    id: null,
    title: '',
    slug: '',
    category_key: 'primeros-pasos',
    summary: '',
    body: '',
    estimated_read_minutes: 4,
    sort_order: 0,
    is_published: true,
};

export default function HelpCenter({ auth, articles = [], categories = [], supportWhatsappUrl }) {
    const [selectedArticle, setSelectedArticle] = useState(null);
    const [activeCategory, setActiveCategory] = useState('all');
    const { data, setData, post, put, processing, reset, errors } = useForm(emptyArticle);

    const filteredArticles = useMemo(() => {
        return activeCategory === 'all'
            ? articles
            : articles.filter((article) => article.category_key === activeCategory);
    }, [activeCategory, articles]);

    const openCreate = () => {
        setSelectedArticle(null);
        reset();
        setData({ ...emptyArticle, category_key: categories[0]?.key || 'primeros-pasos' });
    };

    const openEdit = (article) => {
        setSelectedArticle(article);
        setData({
            id: article.id,
            title: article.title || '',
            slug: article.slug || '',
            category_key: article.category_key || 'primeros-pasos',
            summary: article.summary || '',
            body: article.body || '',
            estimated_read_minutes: article.estimated_read_minutes || 4,
            sort_order: article.sort_order || 0,
            is_published: Boolean(article.is_published),
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (selectedArticle?.id) {
            put(route('help-center.update', selectedArticle.id), { preserveScroll: true });
            return;
        }

        post(route('help-center.store'), {
            preserveScroll: true,
            onSuccess: () => openCreate(),
        });
    };

    const remove = (article) => {
        if (!window.confirm(`¿Eliminar "${article.title}" del centro de ayuda?`)) return;

        router.delete(route('help-center.destroy', article.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Centro de ayuda</h2>}
        >
            <Head title="Centro de ayuda" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl bg-slate-900 p-6 text-white shadow-sm">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-[0.28em] text-cyan-200">MindMeet Docs</p>
                                <h1 className="mt-2 text-3xl font-black">Administra la guía de ayuda para psicólogos</h1>
                                <p className="mt-2 max-w-3xl text-sm text-slate-300">
                                    Crea, publica y ordena artículos del Centro de Ayuda. Todos los artículos empujan al psicólogo a soporte por WhatsApp.
                                </p>
                            </div>

                            <div className="rounded-xl bg-white/10 px-4 py-3 text-sm">
                                <p className="text-cyan-100">Canal de ayuda</p>
                                <a href={supportWhatsappUrl} target="_blank" rel="noreferrer" className="font-semibold text-white underline">
                                    Abrir WhatsApp de soporte
                                </a>
                            </div>
                        </div>
                    </section>

                    <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                            <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900">Artículos actuales</h2>
                                    <p className="text-sm text-gray-500">Organiza la documentación por categoría y controla qué se publica.</p>
                                </div>
                                <PrimaryButton onClick={openCreate}>Nuevo artículo</PrimaryButton>
                            </div>

                            <div className="mb-4 flex flex-wrap gap-2">
                                <CategoryChip
                                    active={activeCategory === 'all'}
                                    label={`Todos (${articles.length})`}
                                    onClick={() => setActiveCategory('all')}
                                />
                                {categories.map((category) => (
                                    <CategoryChip
                                        key={category.key}
                                        active={activeCategory === category.key}
                                        label={category.name}
                                        onClick={() => setActiveCategory(category.key)}
                                    />
                                ))}
                            </div>

                            <div className="space-y-3">
                                {filteredArticles.map((article) => (
                                    <article key={article.id} className="rounded-xl border border-gray-100 p-4">
                                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="font-semibold text-gray-900">{article.title}</h3>
                                                    <span className={`rounded-full px-2 py-1 text-xs font-bold ${article.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                                        {article.is_published ? 'Publicado' : 'Borrador'}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-sm text-gray-500">{article.category_name} · {article.estimated_read_minutes} min · Orden {article.sort_order}</p>
                                                <p className="mt-2 text-sm text-gray-700">{article.summary}</p>
                                                <p className="mt-2 text-xs text-gray-400">Slug: {article.slug} · Actualizado: {article.updated_at}</p>
                                            </div>

                                            <div className="flex gap-2">
                                                <SecondaryButton onClick={() => openEdit(article)}>Editar</SecondaryButton>
                                                <SecondaryButton onClick={() => remove(article)} className="!border-red-200 !text-red-600 hover:!bg-red-50">
                                                    Eliminar
                                                </SecondaryButton>
                                            </div>
                                        </div>
                                    </article>
                                ))}

                                {!filteredArticles.length && (
                                    <p className="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                        No hay artículos en esta categoría todavía.
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900">
                                        {selectedArticle ? 'Editar artículo' : 'Crear artículo'}
                                    </h2>
                                    <p className="text-sm text-gray-500">Escribe contenido claro y práctico. El CTA de soporte por WhatsApp ya se agrega en el front.</p>
                                </div>
                                {selectedArticle && (
                                    <SecondaryButton onClick={openCreate}>Nuevo</SecondaryButton>
                                )}
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <Field label="Título" error={errors.title}>
                                    <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                                </Field>

                                <Field label="Slug" error={errors.slug}>
                                    <input value={data.slug} onChange={(event) => setData('slug', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="Se genera si lo dejas vacío" />
                                </Field>

                                <Field label="Categoría" error={errors.category_key}>
                                    <select value={data.category_key} onChange={(event) => setData('category_key', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                                        {categories.map((category) => (
                                            <option key={category.key} value={category.key}>{category.name}</option>
                                        ))}
                                    </select>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field label="Minutos estimados" error={errors.estimated_read_minutes}>
                                        <input type="number" min="1" max="60" value={data.estimated_read_minutes} onChange={(event) => setData('estimated_read_minutes', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                                    </Field>
                                    <Field label="Orden" error={errors.sort_order}>
                                        <input type="number" min="0" max="9999" value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                                    </Field>
                                </div>

                                <Field label="Resumen" error={errors.summary}>
                                    <textarea value={data.summary} onChange={(event) => setData('summary', event.target.value)} rows={3} className="w-full rounded-lg border-slate-200 text-sm" />
                                </Field>

                                <Field label="Contenido" error={errors.body}>
                                    <textarea value={data.body} onChange={(event) => setData('body', event.target.value)} rows={16} className="w-full rounded-lg border-slate-200 text-sm" />
                                </Field>

                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={data.is_published} onChange={(event) => setData('is_published', event.target.checked)} className="rounded border-gray-300" />
                                    Publicar artículo
                                </label>

                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Guardando...' : selectedArticle ? 'Guardar cambios' : 'Crear artículo'}
                                </PrimaryButton>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

function CategoryChip({ active, label, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${active ? 'bg-sky-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
        >
            {label}
        </button>
    );
}
