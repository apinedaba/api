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
        name: user.name,
        email: user.email,
    });
    const [modal, setModal] = useState(false);
    const [psico, setPsico] = useState(psicologo);
    const [desicion, setDesicion] = useState('');
    const approveEducacion = (cedula) => {
        setDesicion('approved');
        let educacion = psicologo?.educacion?.escuelas?.find((escuela) => escuela?.cedula === cedula);
        educacion.status = 'approved';
        setData({
            ...psicologo,
            educacion: {
                ...psicologo?.educacion,
                escuelas: psicologo?.educacion?.escuelas?.map((escuela) => escuela?.cedula === cedula ? educacion : escuela)
            }
        });
        setModal(true);
    };

    const rejectEducacion = (cedula) => {
        setDesicion('rejected');
        let educacion = psicologo?.educacion?.escuelas?.find((escuela) => escuela?.cedula === cedula);
        educacion.status = 'rejected';
        setData({
            ...psicologo,
            educacion: {
                ...psicologo?.educacion,
                escuelas: psicologo?.educacion?.escuelas?.map((escuela) => escuela?.cedula === cedula ? educacion : escuela)
            }
        });
        setModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        setPsico(data);
        put(route('psicologo.update', psicologo.id));
        setModal(false);
    };

    return (
        <section className={"p-4 sm:p-8 bg-white shadow sm:rounded-lg"}>
            <h2 className="font-semibold text-xl text-gray-800 leading-tight mb-4">Educacion</h2>
            <Modal show={modal} onClose={() => setModal(false)}>
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Estas seguro que quieres {desicion === 'approved' ? 'aprobar' : 'rechazar'} esta educacion
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Una vez {desicion === 'approved' ? 'aprobada' : 'rechazada'} la educacion solo un superadministrador podra {desicion === 'approved' ? 'aprobar' : 'rechazar'}la, y el usuario sera notificado de esta decision
                    </p>


                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={() => setModal(false)}>Cancelar</SecondaryButton>

                        <PrimaryButton className="ms-3" disabled={processing}>
                            {desicion === 'approved' ? 'Aprobar educacion' : 'Rechazar educacion'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
            {
                (psico.educacion?.escuelas?.length > 0) ?
                    <>
                        <div className='space-y-3 grid grid-cols-3 gap-4'>
                            {
                                psico.educacion?.escuelas?.map((escuela, index) => (
                                    <div key={index} className='border border-gray-200 p-4 rounded-lg relative'>
                                        <p>{escuela?.cedula}</p>
                                        <p className='font-bold'>{escuela?.institucion}</p>
                                        <p>{escuela?.titulo}</p>
                                        <p className='absolute top-2 right-2 z-10'><span className={`font-bold bg-green-200 px-2 py-1 rounded-full ${escuela?.status === 'approved' && 'bg-green-200'} ${escuela?.status === 'rejected' && 'bg-red-200'} ${escuela?.status === 'pending' && 'bg-yellow-200'}`}>{escuela?.status === 'approved' ? 'Aprobado' : 'Pendiente'}</span></p>
                                        {
                                            escuela?.status === 'pending' && (
                                                <div className='grid grid-cols-2 gap-2'>
                                                    <button onClick={() => approveEducacion(escuela.cedula)} className="mt-2 bg-blue-500 text-white px-2 py-1 rounded-md">Aprobar</button>
                                                    <button onClick={() => rejectEducacion(escuela.cedula)} className="mt-2 bg-red-500 text-white px-2 py-1 rounded-md">Rechazar</button>
                                                </div>
                                            )
                                        }
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

