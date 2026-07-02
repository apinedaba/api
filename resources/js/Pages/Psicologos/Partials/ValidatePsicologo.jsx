import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, usePage, router } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import Modal from '@/Components/Modal';
import { useState } from 'react';
import SecondaryButton from '@/Components/SecondaryButton';

export default function ValidatePsicologo({ psicologo }) {
    const user = usePage().props.auth.user;
    const [processing, setProcessing] = useState(false);
    const [rejectingType, setRejectingType] = useState(null);
    const [rejectReason, setRejectReason] = useState('');

    const documentLabels = {
        cedula: 'cédula profesional',
        ine: 'INE',
        both: 'cédula profesional e INE',
    };

    const verificationStatus = psicologo?.identity_verification_status;
    const hasCedula = Boolean(psicologo?.cedula_selfie_url);
    const hasIne = Boolean(psicologo?.ine_selfie_url);
    const hasBothDocuments = hasCedula && hasIne;
    const canReviewDocuments = ['pending', 'sending'].includes(verificationStatus) && hasBothDocuments;
    const documentsBadge = hasBothDocuments
        ? verificationStatus === 'approved'
            ? 'Documentos aprobados'
            : 'Documentos cargados'
        : hasCedula || hasIne
            ? 'Documento parcial'
            : 'Sin fotos de identificación';
    const statusLabels = {
        approved: 'Aprobado',
        rejected: 'Rechazado',
        pending: 'Pendiente',
        sending: 'En revisión',
    };

    const openRejectModal = (typeValue) => {
        setRejectingType(typeValue);
        setRejectReason('');
    };

    const closeRejectModal = () => {
        if (processing) {
            return;
        }

        setRejectingType(null);
        setRejectReason('');
    };

    const handleValidate = (actionValue, typeValue, reasonValue = '') => {
        setProcessing(true);
        router.patch(route('psicologos.validate', psicologo.id), {
            action: actionValue,
            type: typeValue,
            rejection_reason: reasonValue,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Validación exitosa');
                setProcessing(false);
                setRejectingType(null);
                setRejectReason('');
            },
            onError: (errors) => {
                console.error('Error en validación:', errors);
                setProcessing(false);
            }
        });
    };

    return (
        <section className={"relative"}>
            <label htmlFor="" className='text-center absolute top-0 right-0 z-10 grid grid-cols-2 gap-2'>
                <span className='block text-xs text-gray-500 font-bold bg-gray-200 px-2 py-1 rounded-full'>
                    {documentsBadge}
                </span>
                <span className={`block text-xs text-gray-500 font-bold px-2 py-1 rounded-full ${verificationStatus === 'approved' && 'bg-green-200'} ${verificationStatus === 'rejected' && 'bg-red-200'} ${verificationStatus === 'pending' && 'bg-yellow-200'} ${verificationStatus === 'sending' && 'bg-blue-200'}`}>
                    {statusLabels[verificationStatus] || 'Sin estado'}
                </span>
            </label>
            <div className="mt-6 space-y-6">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {
                        psicologo?.cedula_selfie_url && (
                            <div className='m-auto text-center'>
                                <a href={psicologo?.cedula_selfie_url} target="_blank" rel="noopener noreferrer">
                                    <img src={psicologo?.cedula_selfie_url} className='w-48 h-48 rounded-full object-cover' alt="" />
                                </a>
                                {canReviewDocuments && (
                                    <SecondaryButton
                                        className="mt-3 border-red-200 text-red-700 hover:bg-red-50"
                                        disabled={processing}
                                        onClick={() => openRejectModal('cedula')}
                                    >
                                        Rechazar cédula
                                    </SecondaryButton>
                                )}
                            </div>
                        )
                    }
                    {
                        psicologo?.ine_selfie_url && (
                            <div className='m-auto text-center'>
                                <a href={psicologo?.ine_selfie_url} target="_blank" rel="noopener noreferrer">
                                    <img src={psicologo?.ine_selfie_url} className='w-48 h-48 rounded-full object-cover' alt="" />
                                </a>
                                {canReviewDocuments && (
                                    <SecondaryButton
                                        className="mt-3 border-red-200 text-red-700 hover:bg-red-50"
                                        disabled={processing}
                                        onClick={() => openRejectModal('ine')}
                                    >
                                        Rechazar INE
                                    </SecondaryButton>
                                )}
                            </div>
                        )
                    }
                </div>
                <div className="flex items-center gap-4 justify-center">
                    {
                        !psicologo?.cedula_selfie_url || !psicologo?.ine_selfie_url ? (
                            <PrimaryButton
                                className="ml-4"
                                disabled={processing}
                                onClick={(e) => {
                                    e.preventDefault();
                                    router.post(route('user.psicologo.solicitud', psicologo.id));
                                }}
                            >
                                {processing ? 'Solicitando Imagenes de verificacion...' : 'Solicitar Imagenes de verificacion'}
                            </PrimaryButton>
                        ) : canReviewDocuments ? (
                            <div className='grid grid-cols-1 gap-3 sm:grid-cols-2'>
                                <PrimaryButton
                                    className="bg-green-600 hover:bg-green-700"
                                    disabled={processing}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        handleValidate('approve', 'both');
                                    }}
                                >
                                    {processing ? 'Aprobando...' : 'Aprobar'}
                                </PrimaryButton>
                                <PrimaryButton
                                    className="bg-red-600 hover:bg-red-700"
                                    disabled={processing}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        openRejectModal('both');
                                    }}
                                >
                                    Rechazar ambos
                                </PrimaryButton>
                            </div>
                        ) : ""
                    }
                </div>
            </div>

            <Modal show={Boolean(rejectingType)} onClose={closeRejectModal} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900">
                        Rechazar {documentLabels[rejectingType] || 'documento'}
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        El psicólogo recibirá un correo indicando qué documento debe volver a subir. Puedes agregar una nota para explicarle el problema.
                    </p>

                    <label className="mt-5 block">
                        <span className="text-sm font-semibold text-gray-700">Motivo o nota para el psicólogo</span>
                        <textarea
                            value={rejectReason}
                            onChange={(event) => setRejectReason(event.target.value)}
                            rows="4"
                            className="mt-2 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Ej. La imagen está borrosa, no se distingue el nombre o el documento está cortado."
                        />
                    </label>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton disabled={processing} onClick={closeRejectModal}>
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton
                            className="bg-red-600 hover:bg-red-700"
                            disabled={processing}
                            onClick={() => handleValidate('reject', rejectingType, rejectReason)}
                        >
                            {processing ? 'Rechazando...' : 'Confirmar rechazo'}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </section>
    );
}
