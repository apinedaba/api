import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import RichTextEditor from '@/Components/RichTextEditor';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const emptyPost = {
    title: '', slug: '', excerpt: '', content: '', author_name: 'Equipo MindMeet',
    category: '', tags: '', cover_image_url: '', cover_image: null,
    cover_image_alt: '', meta_title: '', meta_description: '', status: 'draft',
    is_featured: false, published_at: '',
};

export default function BlogPosts({ auth, posts = [], categories = [] }) {
    const [editing, setEditing] = useState(null);
    const [showEditor, setShowEditor] = useState(false);
    const stats = useMemo(() => ({
        total: posts.length,
        published: posts.filter((post) => post.status === 'published').length,
        drafts: posts.filter((post) => post.status === 'draft').length,
        featured: posts.filter((post) => post.is_featured).length,
    }), [posts]);

    const openEditor = (post = null) => {
        setEditing(post);
        setShowEditor(true);
    };

    const remove = (post) => {
        if (window.confirm(`¿Eliminar el artículo "${post.title}"?`)) {
            router.delete(route('blog-posts.destroy', post.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl font-semibold text-gray-800">Blog</h2>}>
            <Head title="Blog" />
            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[.24em] text-blue-700">Contenido editorial</p>
                                <h1 className="mt-1 text-3xl font-black text-slate-950">Blog de MindMeet</h1>
                                <p className="mt-2 max-w-2xl text-sm text-slate-600">Crea, programa y publica artículos que aparecerán automáticamente en el sitio público.</p>
                            </div>
                            <PrimaryButton onClick={() => openEditor()}>Nuevo artículo</PrimaryButton>
                        </div>
                        <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Metric label="Total" value={stats.total} />
                            <Metric label="Publicados" value={stats.published} tone="emerald" />
                            <Metric label="Borradores" value={stats.drafts} tone="amber" />
                            <Metric label="Destacados" value={stats.featured} tone="violet" />
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        {posts.length ? posts.map((post) => (
                            <article key={post.id} className="flex flex-col gap-4 border-b border-slate-100 p-5 last:border-0 md:flex-row md:items-center">
                                <div className="h-28 w-full shrink-0 overflow-hidden rounded-2xl bg-slate-100 md:w-44">
                                    {post.cover_image_url
                                        ? <img src={post.cover_image_url} alt={post.cover_image_alt || post.title} className="h-full w-full object-cover" />
                                        : <div className="flex h-full items-center justify-center text-xs font-bold text-slate-400">Sin portada</div>}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold">
                                        <span className={post.status === 'published' ? 'rounded-full bg-emerald-100 px-3 py-1 text-emerald-700' : 'rounded-full bg-amber-100 px-3 py-1 text-amber-700'}>
                                            {post.status === 'published' ? 'Publicado' : 'Borrador'}
                                        </span>
                                        {post.is_featured && <span className="rounded-full bg-violet-100 px-3 py-1 text-violet-700">Destacado</span>}
                                        {post.category && <span className="text-slate-500">{post.category}</span>}
                                    </div>
                                    <h2 className="mt-2 truncate text-lg font-black text-slate-950">{post.title}</h2>
                                    <p className="mt-1 line-clamp-2 text-sm text-slate-600">{post.excerpt}</p>
                                    <p className="mt-2 text-xs text-slate-400">/blog/{post.slug} · Actualizado {post.updated_at}</p>
                                </div>
                                <div className="flex shrink-0 gap-3 text-sm font-bold">
                                    <button type="button" onClick={() => openEditor(post)} className="rounded-xl bg-blue-50 px-4 py-2 text-blue-700 hover:bg-blue-100">Editar</button>
                                    <button type="button" onClick={() => remove(post)} className="rounded-xl bg-red-50 px-4 py-2 text-red-600 hover:bg-red-100">Eliminar</button>
                                </div>
                            </article>
                        )) : <div className="p-12 text-center text-slate-500">Todavía no hay artículos. Crea el primero para comenzar.</div>}
                    </section>
                </div>
            </div>

            <Modal show={showEditor} onClose={() => setShowEditor(false)} maxWidth="5xl">
                <PostEditor post={editing} categories={categories} onClose={() => setShowEditor(false)} />
            </Modal>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value, tone = 'blue' }) {
    const colors = { blue: 'bg-blue-50 text-blue-700', emerald: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', violet: 'bg-violet-50 text-violet-700' };
    return <div className={`rounded-2xl p-4 ${colors[tone]}`}><p className="text-xs font-black uppercase tracking-wider">{label}</p><p className="mt-1 text-3xl font-black">{value}</p></div>;
}

function PostEditor({ post, categories, onClose }) {
    const { data, setData, errors, processing, reset } = useForm({ ...emptyPost, ...post, cover_image: null });
    const [showPreview, setShowPreview] = useState(false);
    const [autosaveStatus, setAutosaveStatus] = useState('');
    const draftKey = `mindmeet-blog-draft-${post?.id || 'new'}`;

    useEffect(() => {
        const saved = window.localStorage.getItem(draftKey);
        if (!saved) return;
        try {
            const draft = JSON.parse(saved);
            if ((draft.title || draft.content) && window.confirm('Encontramos cambios guardados automáticamente. ¿Quieres recuperarlos?')) {
                Object.entries(draft).forEach(([key, value]) => setData(key, value));
            }
        } catch {
            window.localStorage.removeItem(draftKey);
        }
    }, []);

    useEffect(() => {
        setAutosaveStatus('Guardando...');
        const timer = window.setTimeout(() => {
            const { cover_image, ...serializable } = data;
            if (serializable.title || serializable.content) {
                window.localStorage.setItem(draftKey, JSON.stringify(serializable));
                setAutosaveStatus('Borrador guardado');
            } else {
                window.localStorage.removeItem(draftKey);
                setAutosaveStatus('');
            }
        }, 900);
        return () => window.clearTimeout(timer);
    }, [data, draftKey]);

    const submit = (event) => {
        event.preventDefault();
        router.post(post?.id ? route('blog-posts.update', post.id) : route('blog-posts.store'), {
            ...data, _method: post?.id ? 'put' : 'post',
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { window.localStorage.removeItem(draftKey); reset(); onClose(); },
        });
    };

    return (
        <form onSubmit={submit} className="max-h-[90vh] overflow-y-auto p-6">
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                <p className="text-xs font-black uppercase tracking-[.2em] text-blue-700">{post ? 'Editar artículo' : 'Nuevo artículo'}</p>
                <h2 className="text-2xl font-black text-slate-950">Contenido del blog</h2>
                <p className="mt-1 text-xs font-semibold text-emerald-600">{autosaveStatus}</p>
                </div>
                <button type="button" onClick={() => setShowPreview(true)} className="rounded-xl bg-violet-50 px-5 py-2 text-sm font-bold text-violet-700 hover:bg-violet-100">Vista previa</button>
            </div>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                <div className="space-y-5">
                    <Field label="Título" error={errors.title}><input value={data.title} onChange={e => setData('title', e.target.value)} className="input" placeholder="Cómo cuidar tu salud mental todos los días" /></Field>
                    <Field label="Extracto para tarjetas" error={errors.excerpt}><textarea value={data.excerpt} onChange={e => setData('excerpt', e.target.value)} rows="3" className="input" placeholder="Una introducción breve que invite a leer..." /></Field>
                    <Field label="Contenido" error={errors.content} hint="Usa la barra para dar formato, agregar enlaces e insertar imágenes desde Cloudinary."><RichTextEditor value={data.content} onChange={value => setData('content', value)} /></Field>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Meta título" error={errors.meta_title}><input value={data.meta_title || ''} maxLength="70" onChange={e => setData('meta_title', e.target.value)} className="input" /></Field>
                        <Field label="Meta descripción" error={errors.meta_description}><textarea value={data.meta_description || ''} maxLength="170" onChange={e => setData('meta_description', e.target.value)} rows="2" className="input" /></Field>
                    </div>
                </div>
                <aside className="space-y-5">
                    <Field label="Estado" error={errors.status}><select value={data.status} onChange={e => setData('status', e.target.value)} className="input"><option value="draft">Borrador</option><option value="published">Publicado</option></select></Field>
                    <Field label="Fecha de publicación" error={errors.published_at}><input type="datetime-local" value={data.published_at || ''} onChange={e => setData('published_at', e.target.value)} className="input" /></Field>
                    <Field label="Dirección pública del artículo" error={errors.slug} hint="Si dejas el slug vacío, se genera automáticamente desde el título."><div className="flex items-center rounded-xl border border-slate-200 bg-white"><span className="pl-3 text-sm text-slate-400">mindmeet.com.mx/blog/</span><input value={data.slug || ''} onChange={e => setData('slug', e.target.value)} className="min-w-0 flex-1 border-0 bg-transparent text-sm focus:ring-0" placeholder="mi-articulo" /></div></Field>
                    <Field label="Autor" error={errors.author_name}><input value={data.author_name || ''} onChange={e => setData('author_name', e.target.value)} className="input" /></Field>
                    <Field label="Categoría" error={errors.category}><input list="blog-categories" value={data.category || ''} onChange={e => setData('category', e.target.value)} className="input" /><datalist id="blog-categories">{categories.map(category => <option key={category} value={category} />)}</datalist></Field>
                    <Field label="Etiquetas" error={errors.tags} hint="Separadas por comas."><input value={data.tags || ''} onChange={e => setData('tags', e.target.value)} className="input" placeholder="ansiedad, bienestar, terapia" /></Field>
                    <Field label="Imagen de portada" error={errors.cover_image} hint="JPG, PNG o WebP. Máximo 8 MB."><input type="file" accept="image/jpeg,image/png,image/webp" onChange={e => setData('cover_image', e.target.files?.[0] || null)} className="input" /></Field>
                    <Field label="URL externa de la imagen" error={errors.cover_image_url} hint="Opcional. Puedes pegar una URL de Cloudinary en lugar de subir un archivo."><input type="url" value={data.cover_image_url || ''} onChange={e => setData('cover_image_url', e.target.value)} className="input" placeholder="https://res.cloudinary.com/..." /></Field>
                    {data.cover_image_url && <div><span className="mb-1 block text-xs font-black uppercase tracking-wider text-slate-500">Portada actual</span><img src={data.cover_image_url} alt={data.cover_image_alt || data.title} className="aspect-video w-full rounded-2xl object-cover" /></div>}
                    <Field label="Texto alternativo" error={errors.cover_image_alt}><input value={data.cover_image_alt || ''} onChange={e => setData('cover_image_alt', e.target.value)} className="input" /></Field>
                    <label className="flex items-center gap-3 rounded-2xl bg-violet-50 p-4 text-sm font-bold text-violet-800"><input type="checkbox" checked={Boolean(data.is_featured)} onChange={e => setData('is_featured', e.target.checked)} className="rounded border-violet-300 text-violet-600" />Destacar este artículo</label>
                </aside>
            </div>
            <div className="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="button" onClick={onClose} className="rounded-xl border border-slate-200 px-5 py-2 text-sm font-bold text-slate-600">Cancelar</button>
                <PrimaryButton disabled={processing}>{post ? 'Guardar cambios' : 'Crear artículo'}</PrimaryButton>
            </div>
            {showPreview && <BlogPreview post={data} onClose={() => setShowPreview(false)} />}
        </form>
    );
}

function BlogPreview({ post, onClose }) {
    return (
        <div className="fixed inset-0 z-[100] overflow-y-auto bg-slate-950/80 p-4 sm:p-8" onClick={onClose}>
            <div className="mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl" onClick={event => event.stopPropagation()}>
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                    <div><p className="text-xs font-black uppercase tracking-wider text-violet-700">Vista previa</p><p className="text-sm text-slate-500">Así se verá el artículo publicado</p></div>
                    <button type="button" onClick={onClose} className="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">Cerrar</button>
                </div>
                <header className="bg-gradient-to-br from-sky-50 via-white to-violet-50 px-6 py-14 text-center sm:px-12">
                    {post.category && <p className="text-xs font-black uppercase tracking-[.25em] text-blue-700">{post.category}</p>}
                    <h1 className="mx-auto mt-4 max-w-4xl text-4xl font-black leading-tight text-slate-950">{post.title || 'Título del artículo'}</h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-600">{post.excerpt || 'El extracto aparecerá en este espacio.'}</p>
                    <p className="mt-6 text-sm text-slate-500">Por {post.author_name || 'Equipo MindMeet'}</p>
                </header>
                {post.cover_image_url && <div className="mx-auto max-w-4xl px-6"><img src={post.cover_image_url} alt={post.cover_image_alt || post.title} className="aspect-[16/8] w-full rounded-[2rem] object-cover shadow-xl" /></div>}
                <div className="blog-editor-content mx-auto max-w-3xl px-6 py-14 text-lg leading-9 text-slate-700" dangerouslySetInnerHTML={{ __html: post.content || '<p>El contenido aparecerá aquí.</p>' }} />
            </div>
        </div>
    );
}

function Field({ label, error, hint, children }) {
    return <label className="block"><span className="mb-1 block text-xs font-black uppercase tracking-wider text-slate-500">{label}</span>{children}{hint && <span className="mt-1 block text-xs text-slate-400">{hint}</span>}{error && <span className="mt-1 block text-xs font-semibold text-red-600">{error}</span>}</label>;
}
