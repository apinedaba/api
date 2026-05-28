import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import { Head, router, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-green-100 text-green-700',
    dismissed: 'bg-gray-100 text-gray-500',
};

export default function MinderReports({ auth, reports, current_status }) {
    const [resolving, setResolving] = useState(null);
    const { data, setData, patch, processing, reset } = useForm({
        action: 'resolve',
        reason: '',
        expires_at: '',
    });

    const handleResolve = (e) => {
        e.preventDefault();
        patch(route('minder.reports.resolve', resolving.id), {
            preserveScroll: true,
            onSuccess: () => { reset(); setResolving(null); },
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
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('minder.groups.index')} className="text-sm text-blue-600 hover:underline">← Grupos</Link>
                    <span className="text-gray-400">/</span>
                    <h2 className="font-semibold text-xl text-gray-800">Reportes de mensajes</h2>
                </div>
            }
        >
            <Head title="Reportes Minder" />
            <div className="py-8">
                <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex gap-2 mb-4 border-b border-gray-200">
                        {tabs.map(tab => (
                            <button key={tab.key}
                                onClick={() => router.get(route('minder.reports.index'), { status: tab.key }, { preserveState: true })}
                                className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition ${current_status === tab.key ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="bg-white shadow sm:rounded-lg divide-y divide-gray-100">
                        {reports.data?.length === 0 && (
                            <p className="py-10 text-center text-sm text-slate-400">Sin reportes.</p>
                        )}
                        {reports.data?.map(report => (
                            <div key={report.id} className="p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${statusColors[report.status]}`}>
                                                {report.status === 'pending' ? 'Pendiente' : report.status === 'resolved' ? 'Resuelto' : 'Descartado'}
                                            </span>
                                            <span className="text-xs text-slate-400">
                                                Grupo: <strong>{report.message?.group?.name}</strong>
                                            </span>
                                        </div>
                                        <blockquote className="text-sm text-slate-700 bg-slate-50 rounded p-3 border-l-4 border-slate-300 my-2 line-clamp-3">
                                            {report.message?.body}
                                        </blockquote>
                                        <p className="text-xs text-slate-500">
                                            Autor: <strong>{report.message?.user?.name}</strong>
                                            {' · '}Reportado por: <strong>{report.reporter?.name}</strong>
                                            {' · '}Razón: {report.reason}
                                        </p>
                                    </div>
                                    {report.status === 'pending' && (
                                        <button onClick={() => setResolving(report)}
                                            className="shrink-0 text-sm text-red-600 font-semibold hover:underline">
                                            Procesar
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Paginación simple */}
                    {reports.last_page > 1 && (
                        <div className="flex justify-center gap-2 mt-6">
                            {Array.from({ length: reports.last_page }, (_, i) => i + 1).map(p => (
                                <button key={p}
                                    onClick={() => router.get(route('minder.reports.index'), { status: current_status, page: p })}
                                    className={`w-8 h-8 text-sm rounded-full ${p === reports.current_page ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border'}`}>
                                    {p}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Modal show={!!resolving} onClose={() => setResolving(null)}>
                <form onSubmit={handleResolve} className="p-6 space-y-4">
                    <h3 className="font-semibold text-lg text-gray-900">Procesar reporte</h3>
                    <div>
                        <InputLabel value="Acción" />
                        <div className="mt-2 space-y-2">
                            {[
                                { value: 'dismiss', label: 'Descartar (el mensaje permanece)' },
                                { value: 'resolve', label: 'Eliminar mensaje' },
                                { value: 'ban', label: 'Eliminar mensaje + banear usuario del grupo' },
                            ].map(opt => (
                                <label key={opt.value} className="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="action" value={opt.value}
                                        checked={data.action === opt.value}
                                        onChange={() => setData('action', opt.value)} />
                                    <span className="text-sm text-slate-700">{opt.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                    {data.action === 'ban' && (
                        <>
                            <div>
                                <InputLabel htmlFor="ban_reason" value="Razón del ban (opcional)" />
                                <TextInput id="ban_reason" className="mt-1 block w-full"
                                    value={data.reason} onChange={e => setData('reason', e.target.value)} />
                            </div>
                            <div>
                                <InputLabel htmlFor="expires_at" value="Expira el (vacío = permanente)" />
                                <TextInput id="expires_at" type="datetime-local" className="mt-1 block w-full"
                                    value={data.expires_at} onChange={e => setData('expires_at', e.target.value)} />
                            </div>
                        </>
                    )}
                    <div className="flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setResolving(null)}>Cancelar</SecondaryButton>
                        <DangerButton disabled={processing}>Confirmar</DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
