import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import Modal from '@/Components/Modal';
export default function ValidatePsicologo({ psicologo }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };
    console.log(psicologo);



    return (
        <section className={"relative"}>
            <label htmlFor="" className='text-center absolute top-0 right-0 z-10 grid grid-cols-2 gap-2'>
                <span className='block text-xs text-gray-500 font-bold bg-gray-200 px-2 py-1 rounded-full'>
                    {
                        (psicologo?.cedula_selfie_url && psicologo?.ine_selfie_url && psicologo?.identity_verification_status === 'pending') ? "Pendiente de verificación" : (psicologo?.cedula_selfie_url && psicologo?.ine_selfie_url && psicologo?.identity_verification_status === 'approved') ? "Aprobado" : "Sin fotos de identificación"
                    }
                </span>
                <span className={`block text-xs text-gray-500 font-bold px-2 py-1 rounded-full ${psicologo?.identity_verification_status === 'approved' && 'bg-green-200'} ${psicologo?.identity_verification_status === 'rejected' && 'bg-red-200'} ${psicologo?.identity_verification_status === 'pending' && 'bg-yellow-200'}`}>
                    {
                        psicologo?.identity_verification_status === 'approved' && "Aprobado"
                    }
                    {
                        psicologo?.identity_verification_status === 'rejected' && "Rechazado"
                    }
                    {
                        psicologo?.identity_verification_status === 'pending' && "Pendiente"
                    }
                </span>
            </label>
            <form onSubmit={submit} className="mt-6 space-y-6 ">
                <div className="grid grid-cols-2">
                    {
                        psicologo?.cedula_selfie_url && (
                            <div className='m-auto'>
                                <a href={psicologo?.cedula_selfie_url} target="_blank" rel="noopener noreferrer">
                                    <img src={psicologo?.cedula_selfie_url} className='w-48 h-48 rounded-full object-cover' alt="" />
                                </a>
                                {psicologo?.identity_verification_status === 'pending' && (
                                    <PrimaryButton className="ml-4" disabled={processing}>
                                        Validar foto de cédula
                                    </PrimaryButton>
                                )}
                            </div>
                        )
                    }
                    {
                        psicologo?.ine_selfie_url && (
                            <div className='m-auto'>
                                <a href={psicologo?.ine_selfie_url} target="_blank" rel="noopener noreferrer">
                                    <img src={psicologo?.ine_selfie_url} className='w-48 h-48 rounded-full object-cover' alt="" />
                                </a>
                                {psicologo?.identity_verification_status === 'pending' && (
                                    <PrimaryButton className="ml-4" disabled={processing}>
                                        Validar foto de INE
                                    </PrimaryButton>
                                )}
                            </div>
                        )
                    }
                </div>
                <div className="flex items-center gap-4 justify-center">
                    {
                        !psicologo?.cedula_selfie_url || !psicologo?.ine_selfie_url ? (
                            <PrimaryButton className="ml-4" disabled={processing} onClick={() => patch(route('psicologos.validate', psicologo.id))}>
                                {processing ? 'Solicitando Imagenes de verificacion...' : 'Solicitar Imagenes de verificacion'}
                            </PrimaryButton>
                        ) : psicologo?.identity_verification_status === 'pending' ? (
                            <div className='grid grid-cols-2'>
                                <PrimaryButton className="ml-4" disabled={processing}>
                                    {processing ? 'Aprobado' : 'Aprobado'}
                                </PrimaryButton>
                                <PrimaryButton className="ml-4" disabled={processing} onClick={() => patch(route('psicologos.validate', psicologo.id))}>
                                    {processing ? 'Verificando...' : 'Verificar'}
                                </PrimaryButton>
                            </div>
                        ) : ""
                    }
                </div>
            </form>
        </section>
    );
}
