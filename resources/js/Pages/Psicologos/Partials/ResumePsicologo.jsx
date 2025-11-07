import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

export default function ResumePsicologo({ psicologo}) {
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
        <section className={""}>
            <header className='relative'>
                <div className={`absolute right-0 top-0 px-4 py-1 rounded-full text-white ${psicologo?.activo ? "bg-green-700" : "bg-red-600"}`}>
                    {
                        psicologo?.activo ? "Activo" : "Inactivo"
                    }
                </div>
                <h2 className="text-lg font-medium text-gray-900">{psicologo?.name}</h2>

                <p className="mt-1 text-sm text-gray-600">
                    {psicologo?.educacion?.littleDescription || "No se contro descripcion corta"}
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="grid grid-cols-2">
                    <div className='m-auto'>
                        <img src={psicologo?.image} className='w-48 h-48 rounded-full object-cover' alt="" />
                    </div>
                    <div className='space-y-3'>
                        <p className=''>
                            <strong>
                                Correo: &nbsp;
                            </strong>
                                {
                                    psicologo?.email
                                }                            
                        </p>
                        <p>
                            <strong>
                                Telefono: &nbsp;
                            </strong>
                            <a href={`tel:${psicologo?.contacto?.telefono}`}>{psicologo?.contacto?.telefono}</a>
                            
                        </p>
                        <p>
                            <strong>
                                Whatsapp: &nbsp;
                            </strong>
                            {
                                psicologo?.contacto?.whatsapp
                            }
                        </p>
                        <p>
                            <strong>
                                Movil: &nbsp;
                            </strong>
                            {
                                psicologo?.contacto?.movil
                            }
                        </p>
                        
                    </div>                    
                </div>
            </form>
        </section>
    );
}
