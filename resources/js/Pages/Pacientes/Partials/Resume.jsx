
import { useForm, usePage } from '@inertiajs/react';
import FotoPerfil from '@/Components/FotoPerfil';

export default function Resumepaciente({ paciente }) {
    const user = usePage().props.auth.user;

    const { patch } = useForm({
        name: user.name,
        email: user.email,
    });

    return (
        <section className={""}>
            <header className='relative'>
                <div className={`absolute right-0 top-0 px-4 py-1 rounded-full text-white ${paciente?.activo ? "bg-green-700" : "bg-red-600"}`}>
                    {
                        paciente?.activo ? "Activo" : "Inactivo"
                    }
                </div>
                <h2 className="text-lg font-medium text-gray-900">{paciente?.name}</h2>

                <p className="mt-1 text-sm text-gray-600">
                    {paciente?.educacion?.littleDescription || "No se contro descripcion corta"}
                </p>
            </header>

            <form className="mt-6 space-y-6">
                <div className="grid grid-cols-2">
                    <div className='m-auto'>
                        <FotoPerfil image={paciente?.image} details={true} />
                    </div>
                    <div className='space-y-3'>
                        <p className=''>
                            <strong>
                                Correo: &nbsp;
                            </strong>
                            {
                                paciente?.email
                            }
                        </p>
                        <p>
                            <strong>
                                Telefono: &nbsp;
                            </strong>
                            <a href={`tel:${paciente?.contacto?.telefono}`}>{paciente?.contacto?.telefono}</a>

                        </p>
                        <p>
                            <strong>
                                Whatsapp: &nbsp;
                            </strong>
                            {
                                paciente?.contacto?.whatsapp
                            }
                        </p>
                        <p>
                            <strong>
                                Movil: &nbsp;
                            </strong>
                            {
                                paciente?.contacto?.movil
                            }
                        </p>

                    </div>
                </div>
            </form>
        </section>
    );
}
