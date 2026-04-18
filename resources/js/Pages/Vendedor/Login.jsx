import { useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function VendedorLogin({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => reset('password');
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('vendedor.login.submit'));
    };

    return (
        <GuestLayout>
            <Head title="Acceso Vendedores" />

            <h2 className="text-center text-lg font-semibold text-gray-700 mb-6">
                Panel de Vendedores
            </h2>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">{status}</div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Contraseña" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-end mt-6">
                    <PrimaryButton disabled={processing}>
                        Iniciar sesión
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
