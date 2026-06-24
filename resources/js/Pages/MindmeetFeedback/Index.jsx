import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const stars = [1, 2, 3, 4, 5];

export default function Index({ auth, feedback, filters, stats }) {
    const [form, setForm] = useState({
        search: filters?.search || '',
        rating: filters?.rating || '',
    });

    const rows = feedback?.data || [];
    const links = feedback?.links || [];
    const average = Number(stats?.average_rating || 0).toFixed(1);

    const activeFilters = useMemo(() => (
        form.search.trim() !== '' || form.rating !== ''
    ), [form.search, form.rating]);

    const submit = (event) => {
        event.preventDefault();
        router.get(route('mindmeet-feedback.index'), form, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const clearFilters = () => {
        setForm({ search: '', rating: '' });
        router.get(route('mindmeet-feedback.index'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Evaluaciones MindMeet</h2>}
        >
            <Head title="Evaluaciones MindMeet" />

            <main className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 p-6 text-white">
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">Voz de psicólogos</p>
                            <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h1 className="text-3xl font-black tracking-tight">Experiencia en MindMeet</h1>
                                    <p className="mt-2 max-w-3xl text-sm text-blue-100">
                                        Revisa calificaciones, comentarios al equipo y oportunidades de mejora detectadas por los psicólogos.
                                    </p>
                                </div>
                                <span className="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold">
                                    Promedio {average}/5
                                </span>
                            </div>
                        </div>

                        <div className="grid gap-4 p-5 md:grid-cols-4">
                            <Kpi title="Evaluaciones" value={stats?.total || 0} />
                            <Kpi title="Promedio" value={`${average}/5`} />
                            <Kpi title="Favorables" value={stats?.positive || 0} />
                            <Kpi title="Atención" value={stats?.needs_attention || 0} />
                        </div>

                        <form onSubmit={submit} className="grid gap-3 border-t border-slate-100 p-5 md:grid-cols-[1fr_180px_auto_auto]">
                            <input
                                type="search"
                                value={form.search}
                                onChange={(event) => setForm((current) => ({ ...current, search: event.target.value }))}
                                placeholder="Buscar por psicólogo o correo"
                                className="rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <select
                                value={form.rating}
                                onChange={(event) => setForm((current) => ({ ...current, rating: event.target.value }))}
                                className="rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">Todas</option>
                                {stars.map((star) => (
                                    <option key={star} value={star}>{star} estrellas</option>
                                ))}
                            </select>
                            <button className="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800">
                                Filtrar
                            </button>
                            {activeFilters ? (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    Limpiar
                                </button>
                            ) : null}
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-5 py-4">Psicólogo</th>
                                        <th className="px-5 py-4">Calificación</th>
                                        <th className="px-5 py-4">Mensaje al equipo</th>
                                        <th className="px-5 py-4">Mejora sugerida</th>
                                        <th className="px-5 py-4">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td colSpan="5" className="px-5 py-10 text-center text-slate-500">
                                                Aún no hay evaluaciones registradas.
                                            </td>
                                        </tr>
                                    ) : rows.map((item) => (
                                        <tr key={item.id} className="align-top">
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-slate-900">{item.user?.name || 'Sin nombre'}</p>
                                                <p className="text-xs text-slate-500">{item.user?.email}</p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <Rating value={item.rating} />
                                            </td>
                                            <td className="max-w-md px-5 py-4 text-slate-700">
                                                {item.team_message || <span className="text-slate-400">Sin mensaje.</span>}
                                            </td>
                                            <td className="max-w-md px-5 py-4 text-slate-700">
                                                {item.improvement_feedback || <span className="text-slate-400">Sin comentario.</span>}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-slate-500">
                                                {new Date(item.created_at).toLocaleDateString('es-MX', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric',
                                                })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {links.length > 3 ? (
                            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 p-4">
                                {links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`rounded-lg px-3 py-2 text-sm font-semibold ${
                                            link.active
                                                ? 'bg-blue-700 text-white'
                                                : link.url
                                                    ? 'border border-slate-200 text-slate-600 hover:bg-slate-50'
                                                    : 'border border-slate-100 text-slate-300'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        ) : null}
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function Kpi({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{title}</p>
            <p className="mt-2 text-2xl font-black text-slate-950">{value}</p>
        </div>
    );
}

function Rating({ value }) {
    return (
        <div>
            <div className="flex gap-1 text-amber-400">
                {stars.map((star) => (
                    <span key={star} className={star <= value ? 'text-amber-400' : 'text-slate-200'}>★</span>
                ))}
            </div>
            <p className="mt-1 text-xs font-semibold text-slate-500">{value}/5</p>
        </div>
    );
}
