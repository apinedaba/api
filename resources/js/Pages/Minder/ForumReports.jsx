import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const statusLabels = {
    pending: 'Pendiente',
    resolved: 'Resuelto',
    dismissed: 'Descartado',
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-green-100 text-green-700',
    dismissed: 'bg-gray-100 text-gray-500',
};

const reasonLabels = {
    patient_privacy: 'Datos identificables de paciente',
    inappropriate: 'Contenido inapropiado',
    misinformation: 'Información clínica riesgosa',
    spam: 'Spam o promoción',
    harassment: 'Acoso o falta de respeto',
    other: 'Otro motivo',
};

export default function ForumReports({ auth, reports, current_status }) {
    const [resolving, setResolving] = useState(null);
    const { data, setData, patch, processing, reset } = useForm({ action: 'hide' });
    const items = reports?.data ?? [];

    const processReport = (event) => {
        event.preventDefault();
        patch(route('minder.forum-reports.resolve', resolving.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setResolving(null);
            },
        });
    };

    const tabs = [
        { key: 'pending', label: 'Pendientes' },
        { key: 'resolved', label: 'Resueltos' },
        { key: 'dismissed', label: 'Descartados' },
        { key: 'all', label: 'Todos' },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800">Mentes en Red — Moderación</h2>}
        >
            <Head title="Moderación Mentes en Red" />
            <div className="py-8">
                <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex gap-2 mb-5 border-b border-gray-200">
                        <span className="border-b-2 border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">Moderación</span>
                        <Link href={route('minder.forum-taxonomy.index')} className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800">
                            Categorías y etiquetas
                        </Link>
                    </div>
                    <div className="flex gap-2 mb-4 border-b border-gray-200 overflow-x-auto">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => router.get(route('minder.forum-reports.index'), { status: tab.key }, { preserveState: true })}
                                className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap ${current_status === tab.key ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="bg-white shadow sm:rounded-lg divide-y divide-gray-100">
                        {items.length === 0 && (
                            <p className="py-10 text-center text-sm text-slate-400">Sin reportes en esta categoría.</p>
                        )}
                        {items.map((report) => (
                            <article key={report.id} className="p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2 mb-2">
                                            <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${statusColors[report.status]}`}>
                                                {statusLabels[report.status]}
                                            </span>
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">
                                                {report.target_type === 'question' ? 'Pregunta' : 'Respuesta'}
                                            </span>
                                            <span className={`text-xs px-2 py-0.5 rounded-full ${report.target?.visible ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`}>
                                                {report.target?.visible ? 'Visible' : 'Oculto'}
                                            </span>
                                        </div>
                                        <p className="text-sm font-semibold text-slate-900">{report.target?.title ?? 'Contenido no disponible'}</p>
                                        <blockquote className="text-sm text-slate-700 bg-slate-50 rounded p-3 border-l-4 border-slate-300 my-2 whitespace-pre-wrap line-clamp-5">
                                            {report.target?.content}
                                        </blockquote>
                                        <p className="text-xs text-slate-500">
                                            Autor: <strong>{report.target?.author?.name ?? 'No disponible'}</strong>
                                            {' · '}Reportado por: <strong>{report.reporter?.name}</strong>
                                        </p>
                                        <p className="text-xs text-slate-500 mt-1">
                                            Motivo: <strong>{reasonLabels[report.reason] ?? report.reason}</strong>
                                            {report.details ? ` · ${report.details}` : ''}
                                        </p>
                                    </div>
                                    {report.target && (report.status === 'pending' || !report.target.visible) && (
                                        <button
                                            onClick={() => {
                                                setData('action', report.target.visible ? 'hide' : 'restore');
                                                setResolving(report);
                                            }}
                                            className="shrink-0 text-sm text-red-600 font-semibold hover:underline"
                                        >
                                            {report.target.visible ? 'Procesar' : 'Revisar'}
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>

                    {reports.last_page > 1 && (
                        <div className="flex justify-center gap-2 mt-6">
                            {Array.from({ length: reports.last_page }, (_, index) => index + 1).map((page) => (
                                <button
                                    key={page}
                                    onClick={() => router.get(route('minder.forum-reports.index'), { status: current_status, page })}
                                    className={`w-8 h-8 text-sm rounded-full ${page === reports.current_page ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border'}`}
                                >
                                    {page}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Modal show={Boolean(resolving)} onClose={() => setResolving(null)}>
                <form onSubmit={processReport} className="p-6 space-y-4">
                    <h3 className="font-semibold text-lg text-gray-900">Procesar reporte</h3>
                    <div className="space-y-2">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="radio" checked={data.action === 'dismiss'} onChange={() => setData('action', 'dismiss')} />
                            <span className="text-sm text-slate-700">Descartar reporte y conservar contenido</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="radio" checked={data.action === 'hide'} onChange={() => setData('action', 'hide')} />
                            <span className="text-sm text-slate-700">Ocultar contenido del foro</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="radio" checked={data.action === 'restore'} onChange={() => setData('action', 'restore')} />
                            <span className="text-sm text-slate-700">Restaurar contenido</span>
                        </label>
                    </div>
                    <div className="flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setResolving(null)}>Cancelar</SecondaryButton>
                        <DangerButton disabled={processing}>Confirmar</DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
