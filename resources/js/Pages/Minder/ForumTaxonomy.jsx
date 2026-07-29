import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyCategory = {
    name: '',
    description: '',
    color: '#0284c7',
    sort_order: 0,
    is_active: true,
};

const emptyTag = {
    name: '',
    sort_order: 0,
    is_active: true,
};

export default function ForumTaxonomy({ auth, categories = [], tags = [] }) {
    const [editingCategory, setEditingCategory] = useState(null);
    const [editingTag, setEditingTag] = useState(null);
    const categoryForm = useForm(emptyCategory);
    const tagForm = useForm(emptyTag);

    const selectCategory = (category = null) => {
        setEditingCategory(category);
        categoryForm.clearErrors();
        categoryForm.setData(category ? {
            name: category.name,
            description: category.description ?? '',
            color: category.color,
            sort_order: category.sort_order,
            is_active: Boolean(category.is_active),
        } : emptyCategory);
    };

    const selectTag = (tag = null) => {
        setEditingTag(tag);
        tagForm.clearErrors();
        tagForm.setData(tag ? {
            name: tag.name,
            sort_order: tag.sort_order,
            is_active: Boolean(tag.is_active),
        } : emptyTag);
    };

    const submitCategory = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => selectCategory() };
        editingCategory
            ? categoryForm.put(route('minder.forum-taxonomy.categories.update', editingCategory.id), options)
            : categoryForm.post(route('minder.forum-taxonomy.categories.store'), options);
    };

    const submitTag = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => selectTag() };
        editingTag
            ? tagForm.put(route('minder.forum-taxonomy.tags.update', editingTag.id), options)
            : tagForm.post(route('minder.forum-taxonomy.tags.store'), options);
    };

    const remove = (type, item) => {
        if (!window.confirm(`¿Eliminar "${item.name}"?`)) return;
        router.delete(route(`minder.forum-taxonomy.${type}.destroy`, item.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800">Mentes en Red — Organización</h2>}
        >
            <Head title="Organización Mentes en Red" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200">
                        <Link href={route('minder.forum-reports.index')} className="px-4 py-2 text-sm font-medium text-slate-500">
                            Moderación
                        </Link>
                        <span className="border-b-2 border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">
                            Categorías y etiquetas
                        </span>
                    </div>

                    <section className="rounded-xl bg-slate-900 px-6 py-5 text-white">
                        <p className="text-xs font-bold uppercase tracking-[0.24em] text-cyan-200">Arquitectura del foro</p>
                        <h1 className="mt-2 text-2xl font-bold">Organiza cómo se descubre el conocimiento</h1>
                        <p className="mt-1 max-w-3xl text-sm text-slate-300">
                            Cada pregunta tiene una categoría principal y hasta cinco etiquetas. Desactiva opciones sin afectar el historial.
                        </p>
                    </section>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <TaxonomySection
                            title="Categorías"
                            subtitle="Agrupación principal de cada pregunta."
                            items={categories}
                            onEdit={selectCategory}
                            onRemove={(item) => remove('categories', item)}
                            renderItem={(category) => (
                                <>
                                    <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: category.color }} />
                                    <ItemCopy item={category} detail={`${category.preguntas_count} preguntas · Orden ${category.sort_order}`} />
                                </>
                            )}
                        >
                            <CategoryForm form={categoryForm} editing={editingCategory} onCancel={() => selectCategory()} onSubmit={submitCategory} />
                        </TaxonomySection>

                        <TaxonomySection
                            title="Etiquetas"
                            subtitle="Temas específicos para búsqueda y filtros."
                            items={tags}
                            onEdit={selectTag}
                            onRemove={(item) => remove('tags', item)}
                            renderItem={(tag) => <ItemCopy item={tag} detail={`${tag.questions_count} preguntas · Orden ${tag.sort_order}`} />}
                        >
                            <TagForm form={tagForm} editing={editingTag} onCancel={() => selectTag()} onSubmit={submitTag} />
                        </TaxonomySection>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function TaxonomySection({ title, subtitle, items, onEdit, onRemove, renderItem, children }) {
    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-lg font-bold text-slate-900">{title}</h2>
                <p className="text-sm text-slate-500">{subtitle}</p>
            </div>
            <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {items.map((item) => (
                    <div key={item.id} className="flex items-center gap-3 px-4 py-3">
                        {renderItem(item)}
                        <span className={`ml-auto rounded-full px-2 py-1 text-xs font-semibold ${item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                            {item.is_active ? 'Activa' : 'Inactiva'}
                        </span>
                        <button type="button" onClick={() => onEdit(item)} className="text-sm font-semibold text-blue-700 hover:underline">Editar</button>
                        <button type="button" onClick={() => onRemove(item)} className="text-sm font-semibold text-red-600 hover:underline">Eliminar</button>
                    </div>
                ))}
            </div>
            {children}
        </section>
    );
}

function ItemCopy({ item, detail }) {
    return (
        <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-slate-900">{item.name}</p>
            <p className="text-xs text-slate-500">{detail}</p>
        </div>
    );
}

function CategoryForm({ form, editing, onCancel, onSubmit }) {
    return (
        <form onSubmit={onSubmit} className="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
            <h3 className="text-sm font-bold text-slate-900">{editing ? 'Editar categoría' : 'Nueva categoría'}</h3>
            <Field label="Nombre" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <Field label="Descripción" error={form.errors.description}>
                <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows={2} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Color" error={form.errors.color}>
                    <input type="color" value={form.data.color} onChange={(event) => form.setData('color', event.target.value)} className="h-10 w-full rounded-lg border border-slate-200 bg-white p-1" />
                </Field>
                <Field label="Orden" error={form.errors.sort_order}>
                    <input type="number" min="0" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
            </div>
            <ActiveToggle checked={form.data.is_active} onChange={(value) => form.setData('is_active', value)} />
            <FormActions editing={editing} processing={form.processing} onCancel={onCancel} />
        </form>
    );
}

function TagForm({ form, editing, onCancel, onSubmit }) {
    return (
        <form onSubmit={onSubmit} className="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
            <h3 className="text-sm font-bold text-slate-900">{editing ? 'Editar etiqueta' : 'Nueva etiqueta'}</h3>
            <div className="grid grid-cols-[1fr_120px] gap-3">
                <Field label="Nombre" error={form.errors.name}>
                    <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Orden" error={form.errors.sort_order}>
                    <input type="number" min="0" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
            </div>
            <ActiveToggle checked={form.data.is_active} onChange={(value) => form.setData('is_active', value)} />
            <FormActions editing={editing} processing={form.processing} onCancel={onCancel} />
        </form>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-semibold text-slate-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

function ActiveToggle({ checked, onChange }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            Disponible para nuevas preguntas
        </label>
    );
}

function FormActions({ editing, processing, onCancel }) {
    return (
        <div className="flex justify-end gap-2">
            {editing && <SecondaryButton type="button" onClick={onCancel}>Cancelar</SecondaryButton>}
            <PrimaryButton disabled={processing}>{editing ? 'Guardar cambios' : 'Crear'}</PrimaryButton>
        </div>
    );
}
