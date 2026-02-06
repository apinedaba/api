import { useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';

export default function DeleteVendedorModal({ vendedor, onClose }) {
    const { delete: destroy, processing } = useForm();

    const submit = (e) => {
        e.preventDefault();

        destroy(route('vendedores.destroy', vendedor.id), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-lg font-semibold text-red-600">
                Eliminar vendedor
            </h2>

            <p className="mt-4 text-sm text-gray-600">
                ¿Estás seguro que deseas eliminar a{' '}
                <strong>{vendedor.nombre}</strong>?
                Esta acción no se puede deshacer.
            </p>

            <div className="mt-6 flex justify-end gap-2">
                <button
                    onClick={onClose}
                    className="px-4 py-2 text-sm text-gray-700 border rounded"
                >
                    Cancelar
                </button>

                <PrimaryButton
                    className="bg-red-600 hover:bg-red-700"
                    disabled={processing}
                    onClick={submit}
                >
                    Sí, eliminar
                </PrimaryButton>
            </div>
        </div>
    );
}
