import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, router, useForm, Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import FotoPerfil from '@/Components/FotoPerfil';

function MessageBubble({ message, isAdmin }) {
    return (
        <div className={`flex gap-3 ${isAdmin ? 'flex-row-reverse' : ''}`}>
            <FotoPerfil image={message.sender?.image} name={message.sender?.name} className="w-8 h-8 rounded-full shrink-0 mt-1" />
            <div className={`max-w-lg ${isAdmin ? 'items-end' : 'items-start'} flex flex-col`}>
                <p className={`text-xs text-slate-500 mb-1 ${isAdmin ? 'text-right' : ''}`}>{message.sender?.name}</p>
                <div className={`px-4 py-2.5 rounded-2xl text-sm leading-relaxed ${isAdmin ? 'bg-blue-600 text-white rounded-tr-sm' : 'bg-slate-100 text-slate-800 rounded-tl-sm'}`}>
                    {message.body}
                </div>
                <p className={`text-[10px] text-slate-400 mt-1 ${isAdmin ? 'text-right' : ''}`}>
                    {new Date(message.created_at).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })}
                </p>
            </div>
        </div>
    );
}

export default function SupportThread({ auth, thread }) {
    const bottomRef = useRef(null);
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [thread.messages]);

    const handleSend = (e) => {
        e.preventDefault();
        if (!data.body.trim()) return;
        post(route('minder.support.messages.store', thread.id), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const isAdminMessage = (msg) => msg.sender_type?.includes('Administrator');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('minder.support.index')} className="text-sm text-blue-600 hover:underline">← Soporte</Link>
                        <span className="text-gray-400">/</span>
                        <div className="flex items-center gap-2">
                            <FotoPerfil image={thread.user?.image} name={thread.user?.name} className="w-7 h-7 rounded-full" />
                            <h2 className="font-semibold text-xl text-gray-800">{thread.user?.name}</h2>
                        </div>
                    </div>
                    {thread.status === 'open' && (
                        <SecondaryButton onClick={() => router.patch(route('minder.support.close', thread.id), {}, { preserveScroll: true })}>
                            Cerrar hilo
                        </SecondaryButton>
                    )}
                </div>
            }
        >
            <Head title={`Soporte: ${thread.user?.name}`} />
            <div className="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8 flex flex-col" style={{ height: 'calc(100vh - 200px)' }}>
                <div className="flex-1 overflow-y-auto bg-white shadow sm:rounded-t-lg p-6 space-y-5">
                    {thread.messages?.length === 0 && (
                        <p className="text-center text-sm text-slate-400">Sin mensajes aún.</p>
                    )}
                    {thread.messages?.map(msg => (
                        <MessageBubble key={msg.id} message={msg} isAdmin={isAdminMessage(msg)} />
                    ))}
                    <div ref={bottomRef} />
                </div>

                {thread.status === 'open' && (
                    <form onSubmit={handleSend} className="bg-white border-t border-gray-200 shadow sm:rounded-b-lg p-4 flex gap-3">
                        <textarea
                            rows={2}
                            value={data.body}
                            onChange={e => setData('body', e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(e); } }}
                            placeholder="Escribe tu respuesta... (Enter para enviar)"
                            className="flex-1 text-sm border-gray-300 rounded-lg resize-none focus:ring-blue-500 focus:border-blue-500"
                        />
                        <PrimaryButton type="submit" disabled={processing || !data.body.trim()} className="self-end">
                            Enviar
                        </PrimaryButton>
                    </form>
                )}
                {thread.status === 'closed' && (
                    <div className="bg-slate-50 border-t border-gray-200 p-4 text-center text-sm text-slate-500 rounded-b-lg">
                        Este hilo está cerrado.
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
