import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';

export default function ModalCita({ show, onClose, patientId, appointment = null }) {
    const [formData, setFormData] = useState({
        patient_id: patientId,
        user_id: '',
        fecha_inicio: '',
        fecha_fin: '',
        tipo: 'presencial',
        state: 'Creado',
        motivo: '',
        observaciones: ''
    });
    const [psychologists, setPsychologists] = useState([]);
    const [loading, setLoading] = useState(false);
    const [loadingAvailability, setLoadingAvailability] = useState(false);
    const [availabilityMessage, setAvailabilityMessage] = useState('');

    useEffect(() => {
        if (show) {
            fetchPsychologists();
            if (appointment) {
                // Modo edición
                setFormData({
                    patient_id: patientId,
                    user_id: appointment.user_id,
                    fecha_inicio: appointment.fecha_inicio.substring(0, 16), // formato datetime-local
                    fecha_fin: appointment.fecha_fin.substring(0, 16),
                    tipo: appointment.tipo,
                    state: appointment.state,
                    motivo: appointment.motivo || '',
                    observaciones: appointment.observaciones || ''
                });
            }
        }
    }, [show, appointment, patientId]);

    const fetchPsychologists = async () => {
        try {
            const response = await axios.get('/admin/api/psicologos/disponibles');
            setPsychologists(response.data.data || []);
        } catch (error) {
            console.error('Error al cargar psicólogos:', error);
            toast.error('Error al cargar psicólogos');
        }
    };

    const checkAvailability = async () => {
        if (!formData.user_id || !formData.fecha_inicio) {
            return;
        }

        setLoadingAvailability(true);
        setAvailabilityMessage('');

        try {
            const date = formData.fecha_inicio.split('T')[0];
            const response = await axios.get(`/admin/api/psicologos/${formData.user_id}/disponibilidad`, {
                params: { date }
            });

            if (response.data.available) {
                setAvailabilityMessage('✓ El psicólogo está disponible en esta fecha');
            } else {
                setAvailabilityMessage('⚠ El psicólogo podría no estar disponible. Verifica su horario.');
            }
        } catch (error) {
            console.error('Error al verificar disponibilidad:', error);
        } finally {
            setLoadingAvailability(false);
        }
    };

    useEffect(() => {
        if (formData.user_id && formData.fecha_inicio) {
            const timeoutId = setTimeout(() => {
                checkAvailability();
            }, 500);
            return () => clearTimeout(timeoutId);
        }
    }, [formData.user_id, formData.fecha_inicio]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));

        // Auto-calcular fecha_fin si se cambia fecha_inicio (por defecto 50 minutos)
        if (name === 'fecha_inicio' && value) {
            const inicio = new Date(value);
            const fin = new Date(inicio.getTime() + 50 * 60000); // +50 minutos
            const finFormatted = fin.toISOString().substring(0, 16);
            setFormData(prev => ({
                ...prev,
                fecha_fin: finFormatted
            }));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        // Validaciones
        if (!formData.user_id) {
            toast.error('Selecciona un psicólogo');
            return;
        }
        if (!formData.fecha_inicio || !formData.fecha_fin) {
            toast.error('Completa las fechas de inicio y fin');
            return;
        }

        const inicio = new Date(formData.fecha_inicio);
        const fin = new Date(formData.fecha_fin);
        if (fin <= inicio) {
            toast.error('La fecha de fin debe ser posterior a la fecha de inicio');
            return;
        }

        setLoading(true);

        try {
            if (appointment) {
                // Actualizar cita existente
                await axios.put(`/admin/api/citas/${appointment.id}`, formData);
                toast.success('Cita actualizada exitosamente');
            } else {
                // Crear nueva cita
                await axios.post('/admin/api/citas', formData);
                toast.success('Cita creada exitosamente');
            }
            onClose(true); // true = refresh data
        } catch (error) {
            console.error('Error al guardar cita:', error);
            toast.error(error.response?.data?.message || 'Error al guardar la cita');
        } finally {
            setLoading(false);
        }
    };

    if (!show) return null;

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-medium text-gray-900">
                        {appointment ? 'Editar Cita' : 'Nueva Cita'}
                    </h3>
                    <button
                        onClick={() => onClose(false)}
                        className="text-gray-400 hover:text-gray-500"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Psicólogo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Psicólogo *
                        </label>
                        <select
                            name="user_id"
                            value={formData.user_id}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="">Seleccionar psicólogo</option>
                            {psychologists.map(psy => (
                                <option key={psy.id} value={psy.id}>
                                    {psy.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Fecha y hora de inicio */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Fecha y Hora de Inicio *
                        </label>
                        <input
                            type="datetime-local"
                            name="fecha_inicio"
                            value={formData.fecha_inicio}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                    </div>

                    {/* Fecha y hora de fin */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Fecha y Hora de Fin *
                        </label>
                        <input
                            type="datetime-local"
                            name="fecha_fin"
                            value={formData.fecha_fin}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                    </div>

                    {/* Mensaje de disponibilidad */}
                    {availabilityMessage && (
                        <div className={`text-sm p-2 rounded ${availabilityMessage.startsWith('✓')
                            ? 'bg-green-50 text-green-700'
                            : 'bg-yellow-50 text-yellow-700'
                            }`}>
                            {availabilityMessage}
                        </div>
                    )}

                    {/* Tipo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Tipo de Cita *
                        </label>
                        <select
                            name="tipo"
                            value={formData.tipo}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="presencial">Presencial</option>
                            <option value="virtual">Virtual</option>
                        </select>
                    </div>

                    {/* Estado */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Estado
                        </label>
                        <select
                            name="state"
                            value={formData.state}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="Creado">Creado</option>
                            <option value="Confirmado">Confirmado</option>
                            <option value="Completado">Completado</option>
                            <option value="Cancelado">Cancelado</option>
                            <option value="No asistió">No asistió</option>
                        </select>
                    </div>

                    {/* Motivo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Motivo de la Cita
                        </label>
                        <input
                            type="text"
                            name="motivo"
                            value={formData.motivo}
                            onChange={handleChange}
                            placeholder="Ej: Sesión de seguimiento"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    {/* Observaciones */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>
                        <textarea
                            name="observaciones"
                            value={formData.observaciones}
                            onChange={handleChange}
                            rows="3"
                            placeholder="Notas adicionales sobre la cita"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    {/* Botones */}
                    <div className="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            onClick={() => onClose(false)}
                            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            disabled={loading}
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                            disabled={loading}
                        >
                            {loading ? 'Guardando...' : (appointment ? 'Actualizar' : 'Crear Cita')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
