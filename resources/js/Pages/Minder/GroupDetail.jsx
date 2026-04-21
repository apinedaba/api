import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import { Head, router, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import FotoPerfil from '@/Components/FotoPerfil';

const roleLabel = (role) => role === 'moderator' ? 'Moderador' : 'Miembro';
const roleBadge = (role) => role === 'moderator'
    ? 'bg-purple-100 text-purple-700'
    : 'bg-blue-50 text-blue-600';

export default function GroupDetail({ auth, group, members, bans, psychologists }) {
    const [showAddModal, setShowAddModal] = useState(false);
    const [showBanModal, setShowBanModal] = useState(null);
    const { data, setData, post, processing, errors, reset } = useForm({ user_id: '', role: 'member' });
    const banForm = useForm({ reason: '', expires_at: '' });

    const handleAddMember = (e) => {
        e.preventDefault();
        post(route('minder.groups.members.add', group.id), {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowAddModal(false); },
        });
    };

    const removeMember = (userId) => {
        if (!confirm('¿Eliminar este miembro del grupo?')) return;
        router.delete(route('minder.groups.members.remove', [group.id, userId]), { preserveScroll: true });
    };

    const updateRole = (userId, currentRole) => {
        router.patch(route('minder.groups.members.role', [group.id, userId]), {
            role: currentRole === 'member' ? 'moderator' : 'member',
        }, { preserveScroll: true });
    };

    const handleBan = (e) => {
        e.preventDefault();
        banForm.post(route('minder.groups.members.ban', [group.id, showBanModal]), {
            preserveScroll: true,
            onSuccess: () => { banForm.reset(); setShowBanModal(null); },
        });
    };

    const unban = (userId) => {
        router.delete(route('minder.groups.members.unban', [group.id, userId]), { preserveScroll: true });
    };

    const bannedIds = new Set(bans.map(b => b.user_id));
    const availablePsychologists = psychologists.filter(p => !members.find(m => m.user_id === p.id));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('minder.groups.index')} className="text-sm text-blue-600 hover:underline">← Grupos</Link>
                    <span className="text-gray-400">/</span>
                    <h2 className="font-semibold text-xl text-gray-800">{group.name}</h2>
                    <span className={`text-xs px-2 py-1 rounded-full font-semibold ${group.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {group.is_active ? 'Activo' : 'Inactivo'}
                    </span>
                </div>
            }
        >
            <Head title={`Grupo: ${group.name}`} />
            <div className="py-8">
                <div className="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Información */}
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        <p className="text-sm text-slate-600">{group.description || 'Sin descripción.'}</p>
                        {group.rules && (
                            <div className="mt-3">
                                <p className="text-xs font-semibold text-slate-500 mb-1">Reglas:</p>
                                <p className="text-sm text-slate-600 whitespace-pre-line">{group.rules}</p>
                            </div>
                        )}
                    </div>

                    {/* Miembros */}
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-gray-800">Miembros ({members.length})</h3>
                            <PrimaryButton onClick={() => setShowAddModal(true)}>+ Agregar</PrimaryButton>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {members.map(member => (
                                <div key={member.id} className="flex items-center gap-3 py-3">
                                    <FotoPerfil image={member.user?.image} name={member.user?.name} className="w-9 h-9 rounded-full" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-semibold text-slate-800 truncate">{member.user?.name}</p>
                                    </div>
                                    <span className={`text-xs px-2 py-0.5 rounded-full ${roleBadge(member.role)}`}>
                                        {roleLabel(member.role)}
                                    </span>
                                    {bannedIds.has(member.user_id) && (
                                        <span className="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">Baneado</span>
                                    )}
                                    <div className="flex gap-2 ml-2">
                                        <button onClick={() => updateRole(member.user_id, member.role)}
                                            className="text-xs text-blue-600 hover:underline">
                                            {member.role === 'member' ? '▲ Moderador' : '▼ Miembro'}
                                        </button>
                                        {bannedIds.has(member.user_id)
                                            ? <button onClick={() => unban(member.user_id)} className="text-xs text-green-600 hover:underline">Desbanear</button>
                                            : <button onClick={() => setShowBanModal(member.user_id)} className="text-xs text-red-600 hover:underline">Banear</button>
                                        }
                                        <button onClick={() => removeMember(member.user_id)} className="text-xs text-slate-400 hover:underline">Eliminar</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal agregar miembro */}
            <Modal show={showAddModal} onClose={() => setShowAddModal(false)}>
                <form onSubmit={handleAddMember} className="p-6 space-y-4">
                    <h3 className="font-semibold text-lg text-gray-900">Agregar miembro</h3>
                    <div>
                        <InputLabel htmlFor="user_id" value="Psicólogo" />
                        <select id="user_id" value={data.user_id} onChange={e => setData('user_id', e.target.value)}
                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                            <option value="">Seleccionar...</option>
                            {availablePsychologists.map(p => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="role" value="Rol" />
                        <select id="role" value={data.role} onChange={e => setData('role', e.target.value)}
                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="member">Miembro</option>
                            <option value="moderator">Moderador</option>
                        </select>
                    </div>
                    <div className="flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setShowAddModal(false)}>Cancelar</SecondaryButton>
                        <PrimaryButton disabled={processing}>Agregar</PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Modal banear */}
            <Modal show={!!showBanModal} onClose={() => setShowBanModal(null)}>
                <form onSubmit={handleBan} className="p-6 space-y-4">
                    <h3 className="font-semibold text-lg text-gray-900">Banear usuario</h3>
                    <div>
                        <InputLabel htmlFor="ban_reason" value="Razón (opcional)" />
                        <textarea id="ban_reason" rows={3}
                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"
                            value={banForm.data.reason} onChange={e => banForm.setData('reason', e.target.value)} />
                    </div>
                    <div>
                        <InputLabel htmlFor="expires_at" value="Expira el (vacío = permanente)" />
                        <TextInput id="expires_at" type="datetime-local" className="mt-1 block w-full"
                            value={banForm.data.expires_at} onChange={e => banForm.setData('expires_at', e.target.value)} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setShowBanModal(null)}>Cancelar</SecondaryButton>
                        <DangerButton disabled={banForm.processing}>Banear</DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
