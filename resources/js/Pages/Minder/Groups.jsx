import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { Head, router, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import DataTable from 'react-data-table-component';

export default function MinderGroups({ auth, groups }) {
    const [showModal, setShowModal] = useState(false);
    const [editingGroup, setEditingGroup] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        description: '',
        type: 'public',
        rules: '',
        max_members: '',
    });

    const openCreate = () => {
        reset();
        setEditingGroup(null);
        setShowModal(true);
    };

    const openEdit = (group) => {
        setEditingGroup(group);
        setData({
            name: group.name,
            description: group.description ?? '',
            type: group.type,
            rules: group.rules ?? '',
            max_members: group.max_members ?? '',
        });
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingGroup) {
            put(route('minder.groups.update', editingGroup.id), {
                preserveScroll: true,
                onSuccess: () => setShowModal(false),
            });
        } else {
            post(route('minder.groups.store'), {
                preserveScroll: true,
                onSuccess: () => { reset(); setShowModal(false); },
            });
        }
    };

    const toggleActive = (group) => {
        router.put(route('minder.groups.update', group.id), {
            is_active: !group.is_active,
        }, { preserveScroll: true });
    };

    const columns = [
        {
            name: 'Grupo',
            grow: 2,
            cell: row => (
                <div className="py-2">
                    <Link href={route('minder.groups.show', row.id)} className="font-bold text-slate-900 hover:text-blue-700">
                        {row.name}
                    </Link>
                    <p className="text-xs text-slate-500 truncate max-w-xs">{row.description}</p>
                </div>
            ),
        },
        {
            name: 'Tipo',
            selector: row => row.type,
            sortable: true,
            cell: row => (
                <span className={`text-xs px-2 py-1 rounded-full font-semibold ${row.type === 'public' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}`}>
                    {row.type === 'public' ? 'Público' : 'Privado'}
                </span>
            ),
        },
        { name: 'Miembros', selector: row => row.members_count, sortable: true },
        { name: 'Mensajes', selector: row => row.messages_count, sortable: true },
        {
            name: 'Estado',
            cell: row => (
                <button
                    onClick={() => toggleActive(row)}
                    className={`text-xs px-2 py-1 rounded-full font-semibold ${row.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}
                >
                    {row.is_active ? 'Activo' : 'Inactivo'}
                </button>
            ),
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex gap-2">
                    <button onClick={() => openEdit(row)} className="text-xs text-blue-600 hover:underline">Editar</button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">Comunidad Minder — Grupos</h2>
                    <PrimaryButton onClick={openCreate}>+ Nuevo grupo</PrimaryButton>
                </div>
            }
        >
            <Head title="Comunidad Minder" />
            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex gap-4 mb-6">
                        <Link href={route('minder.reports.index')} className="text-sm text-blue-700 hover:underline">Reportes pendientes</Link>
                        <Link href={route('minder.metrics.index')} className="text-sm text-blue-700 hover:underline">Métricas</Link>
                        <Link href={route('minder.support.index')} className="text-sm text-blue-700 hover:underline">Soporte</Link>
                    </div>
                    <div className="bg-white shadow sm:rounded-lg overflow-hidden">
                        <DataTable
                            columns={columns}
                            data={groups}
                            pagination
                            highlightOnHover
                            responsive
                            noDataComponent={<p className="py-8 text-sm text-slate-400">Sin grupos aún.</p>}
                        />
                    </div>
                </div>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)}>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <h3 className="font-semibold text-lg text-gray-900">
                        {editingGroup ? 'Editar grupo' : 'Nuevo grupo'}
                    </h3>
                    <div>
                        <InputLabel htmlFor="name" value="Nombre" />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name}
                            onChange={e => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="description" value="Descripción" />
                        <textarea id="description" rows={3}
                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"
                            value={data.description} onChange={e => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="type" value="Tipo" />
                            <select id="type" value={data.type} onChange={e => setData('type', e.target.value)}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="public">Público</option>
                                <option value="private">Privado</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="max_members" value="Máx. miembros (opcional)" />
                            <TextInput id="max_members" type="number" min={2} className="mt-1 block w-full"
                                value={data.max_members} onChange={e => setData('max_members', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <InputLabel htmlFor="rules" value="Reglas del grupo (opcional)" />
                        <textarea id="rules" rows={3}
                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"
                            value={data.rules} onChange={e => setData('rules', e.target.value)} />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" onClick={() => setShowModal(false)}>Cancelar</SecondaryButton>
                        <PrimaryButton disabled={processing}>{processing ? 'Guardando...' : 'Guardar'}</PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
