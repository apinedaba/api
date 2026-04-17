import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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
    const money = (value) =>
        new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
        }).format(Number(value || 0));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Vendedores</h2>}
        >
            <Head title="Vendedores" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div className='flex flex-col gap-3 md:flex-row md:items-center md:justify-between'>
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Red de vendedores</h3>
                                <p className="text-sm text-gray-500">
                                    Cada vendedor tiene un QR unico. Los psicologos que se registran con ese enlace quedan relacionados al vendedor.
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <Link
                                    href={route('seller-commissions')}
                                    className="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
                                >
                                    Ver pagos
                                </Link>
                                <PrimaryButton onClick={() => setShowModal(true)}>Agregar vendedor</PrimaryButton>
                            </div>
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
                        <div className="p-6 text-gray-900 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {
                                vendedores?.length === 0 ? (
                                    <p>No hay vendedores registrados aun</p>
                                ) : (
                                    vendedores.map((vendedor) => (
                                        <div key={vendedor.id} className='border border-gray-200 rounded-2xl p-4 shadow-sm'>

                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <h1 className='text-lg font-semibold'>{vendedor.nombre}</h1>
                                                    <p className='text-sm text-gray-600'>{vendedor.email}</p>
                                                    <p className='text-sm text-gray-600'>{vendedor.telefono}</p>
                                                </div>
                                                <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                                    {money(vendedor.pending_commissions_sum)} pendiente
                                                </span>
                                            </div>

                                            <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                                <MiniStat label="Referidos" value={vendedor.referrals_count || 0} />
                                                <MiniStat label="Activos" value={vendedor.active_referrals_count || 0} />
                                                <MiniStat label="Sin pago" value={vendedor.unpaid_referrals_count || 0} />
                                            </div>

                                            <div className="mt-4 rounded-xl bg-gray-50 p-3">
                                                <p className="text-xs font-bold uppercase tracking-wide text-gray-500">Link del QR</p>
                                                <p className="mt-1 break-all text-xs text-gray-600">{vendedor.registration_url}</p>
                                            </div>

                                            <div className="grid grid-cols-3 gap-3 mt-4">
                                                <a
                                                    href={route('vendedores.qr.download', vendedor.id)}
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

                                            <div className="mt-4 border-t border-gray-100 pt-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Psicologos registrados</p>
                                                <div className="max-h-52 space-y-2 overflow-y-auto pr-1">
                                                    {vendedor.referrals?.length ? vendedor.referrals.map((referral) => (
                                                        <div key={referral.id} className="rounded-lg border border-gray-100 p-3 text-sm">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div>
                                                                    <p className="font-semibold text-gray-900">{referral.psychologist?.name || 'Psicologo sin nombre'}</p>
                                                                    <p className="text-xs text-gray-500">{referral.psychologist?.email}</p>
                                                                </div>
                                                                <span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${referral.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                                                                    {referral.status === 'active' ? 'Pago activo' : 'No ha pagado'}
                                                                </span>
                                                            </div>
                                                            <div className="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-500">
                                                                <span>Registro: {referral.registered_at || 'N/A'}</span>
                                                                <span>Trial fin: {referral.trial_ends_at || referral.psychologist?.trial_ends_at || 'N/A'}</span>
                                                                <span>Suscripcion: {referral.psychologist?.subscription_status || 'Sin pago'}</span>
                                                                <span>Activacion: {referral.first_activated_at || 'Pendiente'}</span>
                                                            </div>
                                                        </div>
                                                    )) : (
                                                        <p className="text-sm text-gray-500">Aun no hay psicologos registrados con este QR.</p>
                                                    )}
                                                </div>
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

function MiniStat({ label, value }) {
    return (
        <div className="rounded-lg bg-blue-50 p-2">
            <p className="font-bold text-blue-900">{value}</p>
            <p className="text-blue-700">{label}</p>
        </div>
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
