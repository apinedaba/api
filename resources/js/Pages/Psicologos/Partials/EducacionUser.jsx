import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import Modal from '@/Components/Modal';
import { useState } from 'react';
import SecondaryButton from '@/Components/SecondaryButton';

export default function EducacionUser({ psicologo }) {
    const user = usePage().props.auth.user;

    const { data, setData, put, errors, processing, recentlySuccessful } = useForm({
        estado: '',
        id: '',
        notas_admin: '',
        user_id: psicologo.id
    });
    const [modal, setModal] = useState(false);
    const [escuelas, setEscuelas] = useState(psicologo?.escuelas);
    const [desicion, setDesicion] = useState('');
    const approveEducacion = (id) => {
        setDesicion('aprobado');
        setData({
            estado: 'aprobado',
            id: id,
            notas_admin: 'Aprobado',
            user_id: psicologo.id
        })
        setModal(true);
    };

    const rejectEducacion = (id) => {
        setDesicion('rechazado');
        setData({
            estado: 'rechazado',
            id: id,
            notas_admin: 'Rechazado',
            user_id: psicologo.id
        })
        setModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('validacion.update', data.id));
        setModal(false);
    };

    return (
        <section className={""}>
            <h2 className="font-semibold text-xl text-gray-800 leading-tight mb-4">Educacion</h2>
            <Modal show={modal} onClose={() => setModal(false)}>
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Estas seguro que quieres {desicion === 'aprobado' ? 'aprobar' : 'rechazar'} esta educacion
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Una vez {desicion === 'aprobado' ? 'aprobada' : 'rechazada'} la educacion solo un superadministrador podra {desicion === 'aprobado' ? 'aprobar' : 'rechazar'}la, y el usuario sera notificado de esta decision
                    </p>


                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={() => setModal(false)}>Cancelar</SecondaryButton>

                        <PrimaryButton className="ms-3" disabled={processing}>
                            {desicion === 'aprobado' ? 'Aprobar educacion' : 'Rechazar educacion'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
            {
                (escuelas?.length > 0) ?
                    <>
                        <div className='space-y-3 grid grid-cols-3 gap-4'>
                            {
                                escuelas?.map((escuela, index) => (
                                    <div key={index} className='border border-gray-200 p-4 rounded-lg relative text-xs pt-4'>
                                        <p className='absolute top-2 right-2 z-10'><span className={`font-bold bg-green-200 px-2 py-1 rounded-full ${escuela?.estado === 'aprobado' && 'bg-green-200'} ${escuela?.estado === 'rechazado' && 'bg-red-200'} ${escuela?.estado === 'pendiente' && 'bg-yellow-200'}`}>
                                            {escuela?.estado === 'aprobado' && 'Aprobado'}
                                            {escuela?.estado === 'rechazado' && 'Rechazado'}
                                            {escuela?.estado === 'pendiente' && 'Pendiente'}
                                        </span></p>
                                        <p>{escuela?.numero_cedula}</p>
                                        <p className='font-bold'>{escuela?.institucion}</p>
                                        <p>{escuela?.carrera}</p>

                                        <div className='grid grid-cols-2 gap-2'>
                                            <p>Notas: {escuela?.notas_admin}</p>
                                            {
                                                (escuela?.estado === 'pendiente' || escuela?.estado === 'rechazado') && (
                                                    <button onClick={() => approveEducacion(escuela.id)} className="mt-2 bg-blue-500 text-white px-2 py-1 rounded-md">Aprobar</button>
                                                )
                                            }
                                            {
                                                (escuela?.estado === 'pendiente' || escuela?.estado === 'aprobado') && (
                                                    <button onClick={() => rejectEducacion(escuela.id)} className="mt-2 bg-red-500 text-white px-2 py-1 rounded-md">Rechazar</button>
                                                )
                                            }
                                        </div>

                                    </div>
                                ))
                            }
                        </div>
                    </>
                    :
                    <div className='space-y-3 grid grid-cols-3 gap-4'>
                        No hay escuelas agregadas
                    </div>
            }
            <PrimaryButton className="mt-2" disabled={processing} onClick={() => patch(route('psicologos.validate', psicologo.id))}>
                {processing ? 'Agregando...' : 'Agregar escuela'}
            </PrimaryButton>
        </section>
    );
}

