import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';
import { toast } from 'react-hot-toast';

export default function ModalAsignarPsicologo({ show, patient, onClose, onSuccess }) {
    const [loading, setLoading] = useState(false);
    const [psychologists, setPsychologists] = useState([]);
    const [selectedPsychologist, setSelectedPsychologist] = useState('');
    const [setAsActive, setSetAsActive] = useState(false);
    const [currentPsychologists, setCurrentPsychologists] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        if (show && patient) {
            fetchPsychologists();
            setCurrentPsychologists(patient.connections || []);
        }
    }, [show, patient]);

    const fetchPsychologists = async () => {
        try {
            const response = await axios.get('/admin/api/psicologos/disponibles');
            if (response.data.success) {
                setPsychologists(response.data.data);
            }
        } catch (error) {
            console.error('Error fetching psychologists:', error);
            toast.error('Error al cargar psicólogos');
        }
    };

    const handleAssign = async (e) => {
        e.preventDefault();

        if (!selectedPsychologist) {
            setError('Debes seleccionar un psicólogo');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const response = await axios.post(`/admin/api/pacientes/${patient.id}/asignar-psicologo`, {
                psychologist_id: selectedPsychologist,
                set_as_active: setAsActive
            });

            if (response.data.success) {
                toast.success(response.data.message || 'Psicólogo asignado exitosamente');
                onSuccess && onSuccess();
                handleClose();
            }
        } catch (error) {
            console.error('Error assigning psychologist:', error);

            if (error.response?.data?.message) {
                toast.error(error.response.data.message);
            } else {
                toast.error('Error al asignar el psicólogo');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleRemovePsychologist = async (psychologistId) => {
        if (!confirm('¿Estás seguro de que quieres remover este psicólogo del paciente?')) {
            return;
        }

        try {
            const response = await axios.delete(`/admin/api/pacientes/${patient.id}/psicologos/${psychologistId}`);

            if (response.data.success) {
                toast.success('Psicólogo removido exitosamente');
                setCurrentPsychologists(prev => prev.filter(conn => conn.user.id !== psychologistId));
                onSuccess && onSuccess();
            }
        } catch (error) {
            console.error('Error removing psychologist:', error);
            toast.error('Error al remover el psicólogo');
        }
    };

    const handleSetActive = async (psychologistId) => {
        try {
            const response = await axios.put(`/admin/api/pacientes/${patient.id}/psicologos/${psychologistId}/activar`);

            if (response.data.success) {
                toast.success('Psicólogo principal actualizado');
                setCurrentPsychologists(prev =>
                    prev.map(conn => ({
                        ...conn,
                        activo: conn.user.id === psychologistId
                    }))
                );
                onSuccess && onSuccess();
            }
        } catch (error) {
            console.error('Error setting active psychologist:', error);
            toast.error('Error al establecer el psicólogo principal');
        }
    };

    const handleClose = () => {
        setSelectedPsychologist('');
        setSetAsActive(false);
        setError('');
        onClose();
    };

    const getAvailablePsychologists = () => {
        const assignedIds = currentPsychologists.map(conn => conn.user?.id);
        return psychologists.filter(p => !assignedIds.includes(p.id));
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="3xl">
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Gestionar Psicólogos</h2>
                        <p className="text-sm text-gray-600 mt-1">
                            Paciente: <span className="font-semibold">{patient?.name}</span>
                        </p>
                    </div>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="space-y-6">
                    {/* Psicólogos actuales */}
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 mb-3">
                            Psicólogos Asignados ({currentPsychologists.length})
                        </h3>

                        {currentPsychologists.length === 0 ? (
                            <div className="bg-gray-50 rounded-lg p-4 text-center text-gray-500">
                                No hay psicólogos asignados a este paciente
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {currentPsychologists.map(connection => (
                                    <div
                                        key={connection.id}
                                        className={`flex items-center justify-between p-4 rounded-lg border ${connection.activo
                                                ? 'border-green-300 bg-green-50'
                                                : 'border-gray-200 bg-white'
                                            }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-semibold">
                                                {connection.user?.name?.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-semibold text-gray-900">
                                                    {connection.user?.name}
                                                    {connection.activo && (
                                                        <span className="ml-2 px-2 py-1 text-xs bg-green-600 text-white rounded-full">
                                                            Principal
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-sm text-gray-600">{connection.user?.email}</p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Estado: {connection.status}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            {!connection.activo && (
                                                <button
                                                    onClick={() => handleSetActive(connection.user.id)}
                                                    className="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600"
                                                    title="Establecer como principal"
                                                >
                                                    Activar
                                                </button>
                                            )}
                                            <button
                                                onClick={() => handleRemovePsychologist(connection.user.id)}
                                                className="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
                                                title="Remover psicólogo"
                                            >
                                                Remover
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Formulario para asignar nuevo psicólogo */}
                    <div className="border-t pt-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-3">
                            Asignar Nuevo Psicólogo
                        </h3>

                        <form onSubmit={handleAssign} className="space-y-4">
                            <div>
                                <InputLabel htmlFor="psychologist_select" value="Seleccionar Psicólogo" />
                                <select
                                    id="psychologist_select"
                                    value={selectedPsychologist}
                                    onChange={(e) => {
                                        setSelectedPsychologist(e.target.value);
                                        setError('');
                                    }}
                                    className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">-- Seleccionar psicólogo --</option>
                                    {getAvailablePsychologists().map(psychologist => (
                                        <option key={psychologist.id} value={psychologist.id}>
                                            {psychologist.name} - {psychologist.email}
                                            {psychologist.especialidad && ` (${psychologist.especialidad})`}
                                        </option>
                                    ))}
                                </select>
                                {error && <InputError message={error} className="mt-2" />}

                                {getAvailablePsychologists().length === 0 && (
                                    <p className="mt-2 text-sm text-gray-500">
                                        Todos los psicólogos disponibles ya están asignados
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center">
                                <input
                                    id="set_as_active"
                                    type="checkbox"
                                    checked={setAsActive}
                                    onChange={(e) => setSetAsActive(e.target.checked)}
                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <label htmlFor="set_as_active" className="ml-2 block text-sm text-gray-900">
                                    Establecer como psicólogo principal
                                </label>
                            </div>

                            <div className="flex justify-end gap-3">
                                <SecondaryButton type="button" onClick={handleClose}>
                                    Cerrar
                                </SecondaryButton>
                                <PrimaryButton
                                    type="submit"
                                    disabled={loading || !selectedPsychologist || getAvailablePsychologists().length === 0}
                                >
                                    {loading ? (
                                        <span className="flex items-center gap-2">
                                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Asignando...
                                        </span>
                                    ) : (
                                        'Asignar Psicólogo'
                                    )}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
