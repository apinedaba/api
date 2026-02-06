import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import DeleteVendedorModal from './Vendedores/DeleteVendedorModal';

export default function Vendedores({ auth, vendedores }) {
    const { flash } = usePage().props;
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [selectedVendedor, setSelectedVendedor] = useState(null);

    {
        flash?.success && (
            <div className="mb-4 text-green-600 text-sm">
                {flash.success}
            </div>
        )
    }

    const [showModal, setShowModal] = useState(false);
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Vendedores</h2>}
        >
            <Head title="Vendedores" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div className='flex justify-end'>
                            <PrimaryButton onClick={() => setShowModal(true)}>Agregar vendedor</PrimaryButton>
                        </div>
                        <Modal show={showModal} onClose={() => setShowModal(false)}>
                            <FormCreateEditVendedor
                                vendedor={selectedVendedor}
                                onClose={() => {
                                    setShowModal(false);
                                    setSelectedVendedor(null);
                                }}
                            />
                        </Modal>
                        <Modal show={showDeleteModal} onClose={() => setShowDeleteModal(false)}>
                            {selectedVendedor && (
                                <DeleteVendedorModal
                                    vendedor={selectedVendedor}
                                    onClose={() => {
                                        setShowDeleteModal(false);
                                        setSelectedVendedor(null);
                                    }}
                                />
                            )}
                        </Modal>
                        <div className="p-6 text-gray-900 grid grid-cols-3">
                            {
                                vendedores?.length === 0 ? (
                                    <p>No hay vendedores registrados aun</p>
                                ) : (
                                    vendedores.map((vendedor) => (
                                        <div key={vendedor.id} className='border border-gray-200 rounded-lg p-4'>

                                            <h1 className='text-lg font-semibold'>{vendedor.nombre}</h1>
                                            <p className='text-sm text-gray-600'>{vendedor.email}</p>
                                            <p className='text-sm text-gray-600'>{vendedor.telefono}</p>
                                            <div className="grid grid-cols-3 gap-3 mt-2">
                                                <a
                                                    href={route('vendedores.qr.image', vendedor.id)}
                                                    className="text-sm text-green-600 underline"
                                                >
                                                    Descargar QR
                                                </a>
                                                <PrimaryButton
                                                    className='bg-blue-600 hover:bg-blue-700 flex justify-center'
                                                    onClick={() => {
                                                        setSelectedVendedor(vendedor);
                                                        setShowModal(true);
                                                    }}
                                                >
                                                    Editar
                                                </PrimaryButton>
                                                <PrimaryButton
                                                    className="bg-red-600 hover:bg-red-700 flex justify-center"
                                                    onClick={() => {
                                                        setSelectedVendedor(vendedor);
                                                        setShowDeleteModal(true);
                                                    }}
                                                >
                                                    Eliminar
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    ))
                                )
                            }
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}


export function FormCreateEditVendedor({ vendedor = null, onClose }) {
    const isEdit = Boolean(vendedor);

    const { data, setData, post, put, processing, errors } = useForm({
        nombre: vendedor?.nombre || '',
        email: vendedor?.email || '',
        telefono: vendedor?.telefono || '',
        password: '',
        password_confirmation: '',
        direccion: vendedor?.direccion || '',
        ciudad: vendedor?.ciudad || '',
        estado: vendedor?.estado || '',
        codigo_postal: vendedor?.codigo_postal || '',
        pais: vendedor?.pais || '',
        rol: vendedor?.rol || 'vendedor',
        imagen: null,
    });

    const handleChange = (e) => {
        const { name, value, type, files } = e.target;
        setData(name, type === 'file' ? files[0] : value);
    };

    const submit = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('vendedores.update', vendedor.id), {
                forceFormData: true,
                onSuccess: () => onClose?.(),
            });
        } else {
            post(route('vendedores.store'), {
                forceFormData: true,
                onSuccess: () => onClose?.(),
            });
        }
    };

    const InputGroupStyles = "w-full flex";

    return (
        <div className="p-6">
            <h1 className="text-lg font-semibold mb-4">
                {isEdit ? 'Editar vendedor' : 'Crear vendedor'}
            </h1>

            <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">

                {/* Nombre */}
                <div className={InputGroupStyles}>
                    <InputLabel>Nombre</InputLabel>
                    <TextInput
                        name="nombre"
                        value={data.nombre}
                        onChange={handleChange}
                        className={errors.nombre && 'border-red-500'}
                    />
                    <InputError message={errors.nombre} />
                </div>

                {/* Email */}
                <div className={InputGroupStyles}>
                    <InputLabel>Email</InputLabel>
                    <TextInput
                        name="email"
                        type="email"
                        value={data.email}
                        onChange={handleChange}
                        className={errors.email && 'border-red-500'}
                    />
                    <InputError message={errors.email} />
                </div>

                {/* Teléfono */}
                <div className={InputGroupStyles}>
                    <InputLabel>Teléfono</InputLabel>
                    <TextInput
                        name="telefono"
                        value={data.telefono}
                        onChange={handleChange}
                        className={errors.telefono && 'border-red-500'}
                    />
                    <InputError message={errors.telefono} />
                </div>

                {/* Password */}
                <div className={InputGroupStyles}>
                    <InputLabel>
                        Password {isEdit && '(opcional)'}
                    </InputLabel>
                    <TextInput
                        name="password"
                        type="password"
                        value={data.password}
                        onChange={handleChange}
                    />
                    <InputError message={errors.password} />
                </div>

                {/* Confirmación */}
                <div className={InputGroupStyles}>
                    <InputLabel>Confirmar Password</InputLabel>
                    <TextInput
                        name="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={handleChange}
                    />
                </div>

                {/* Dirección */}
                <div className={InputGroupStyles}>
                    <InputLabel>Dirección</InputLabel>
                    <TextInput
                        name="direccion"
                        value={data.direccion}
                        onChange={handleChange}
                        className={errors.direccion && 'border-red-500'}
                    />
                    <InputError message={errors.direccion} />
                </div>

                {/* Ciudad */}
                <div className={InputGroupStyles}>
                    <InputLabel>Ciudad</InputLabel>
                    <TextInput
                        name="ciudad"
                        value={data.ciudad}
                        onChange={handleChange}
                        className={errors.ciudad && 'border-red-500'}
                    />
                    <InputError message={errors.ciudad} />
                </div>

                {/* Estado */}
                <div className={InputGroupStyles}>
                    <InputLabel>Estado</InputLabel>
                    <TextInput
                        name="estado"
                        value={data.estado}
                        onChange={handleChange}
                        className={errors.estado && 'border-red-500'}
                    />
                    <InputError message={errors.estado} />
                </div>

                {/* Código Postal */}
                <div className={InputGroupStyles}>
                    <InputLabel>Código Postal</InputLabel>
                    <TextInput
                        name="codigo_postal"
                        value={data.codigo_postal}
                        onChange={handleChange}
                        className={errors.codigo_postal && 'border-red-500'}
                    />
                    <InputError message={errors.codigo_postal} />
                </div>

                {/* País */}
                <div className={InputGroupStyles}>
                    <InputLabel>País</InputLabel>
                    <TextInput
                        name="pais"
                        value={data.pais}
                        onChange={handleChange}
                        className={errors.pais && 'border-red-500'}
                    />
                    <InputError message={errors.pais} />
                </div>

                {/* Rol */}
                <div className={InputGroupStyles}>
                    <InputLabel>Rol</InputLabel>
                    <TextInput
                        name="rol"
                        value={data.rol}
                        onChange={handleChange}
                        className={errors.rol && 'border-red-500'}
                    />
                    <InputError message={errors.rol} />
                </div>


                {/* Imagen */}
                <div className="col-span-full">
                    <InputLabel>Imagen</InputLabel>
                    <TextInput
                        name="imagen"
                        type="file"
                        onChange={handleChange}
                    />
                    <InputError message={errors.imagen} />
                </div>

                <div className="col-span-full flex justify-end mt-6">
                    <PrimaryButton disabled={processing}>
                        {isEdit ? 'Actualizar' : 'Guardar'}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    );
}
