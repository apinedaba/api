import { useRef, useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

export default function ActiveUserForm({ className = '', psicologo }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        post: post,
        processing,
        reset,
        errors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const activeUser = (e) => {
        e.preventDefault();

        post(route('psicologo.active', psicologo.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Desactivar usuario</h2>

                <p className="mt-1 text-sm text-gray-600">
                    Al desactivar al usuario dejara de aparecer en el catalogo publico de psicologos
                </p>
            </header>
            <PrimaryButton onClick={confirmUserDeletion}>Activar cuenta</PrimaryButton>
            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={activeUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Estas seguro que quieres desactivar esta cuenta
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Una vez desactivada la cuenta solo un superadministrador podra reactivarla, y el usuario sera notificado de esta decision
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="password" value="Password" className="sr-only" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder="Password"
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeModal}>Cancelar</SecondaryButton>

                        <PrimaryButton className="ms-3" disabled={processing}>
                            Activar cuenta
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
