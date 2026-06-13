import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import FotoPerfil from '@/Components/FotoPerfil';

export default function MinderSupport({ auth, threads }) {
    const items = threads?.data ?? [];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800">Soporte MindMeet</h2>}
        >
            <Head title="Soporte Minder" />
            <div className="py-8">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-5 flex gap-2 border-b border-slate-200">
                        <span className="border-b-2 border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">Mensajes</span>
                        <Link href={route('minder.support-appointments.index')} className="px-4 py-2 text-sm text-slate-500">Sesiones de apoyo</Link>
                    </div>
                    <div className="bg-white shadow sm:rounded-lg divide-y divide-gray-100">
                        {items.length === 0 && (
                            <p className="py-10 text-center text-sm text-slate-400">Sin hilos de soporte.</p>
                        )}
                        {items.map(thread => {
                            const lastMsg = thread.messages?.[0];
                            return (
                                <Link key={thread.id} href={route('minder.support.show', thread.id)}
                                    className="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 transition">
                                    <FotoPerfil image={thread.user?.image} name={thread.user?.name} className="w-10 h-10 rounded-full shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold text-slate-800 truncate">{thread.user?.name}</p>
                                            {thread.unread_count > 0 && (
                                                <span className="bg-blue-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                                    {thread.unread_count}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-slate-500 truncate">{lastMsg?.body ?? 'Sin mensajes aún.'}</p>
                                    </div>
                                    <span className={`text-xs px-2 py-0.5 rounded-full shrink-0 ${thread.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                        {thread.status === 'open' ? 'Abierto' : 'Cerrado'}
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
