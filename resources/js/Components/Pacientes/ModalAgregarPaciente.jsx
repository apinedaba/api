import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';
import { toast } from 'react-hot-toast';

export default function ModalAgregarPaciente({ show, onClose, onSuccess }) {
    const [loading, setLoading] = useState(false);
    const [psychologists, setPsychologists] = useState([]);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        telefono: '',
        password: '',
        psychologist_id: '',
        activo: true
    });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (show) {
            fetchPsychologists();
        }
    }, [show]);

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

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
        // Limpiar error del campo
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: null }));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            // Preparar datos para enviar
            const dataToSend = {
                name: formData.name,
                email: formData.email,
                contacto: {
                    telefono: formData.telefono
                },
                password: formData.password || formData.telefono,
                activo: formData.activo
            };

            // Solo agregar psychologist_id si se seleccionó uno
            if (formData.psychologist_id) {
                dataToSend.psychologist_id = formData.psychologist_id;
            }

            const response = await axios.post('/admin/api/pacientes', dataToSend);

            if (response.data.success) {
                toast.success(response.data.message || 'Paciente creado exitosamente');
                onSuccess && onSuccess();
                handleClose();
            }
        } catch (error) {
            console.error('Error creating patient:', error);

            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else if (error.response?.data?.message) {
                toast.error(error.response.data.message);
            } else {
                toast.error('Error al crear el paciente');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleClose = () => {
        setFormData({
            name: '',
            email: '',
            telefono: '',
            password: '',
            psychologist_id: '',
            activo: true
        });
        setErrors({});
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-2xl font-bold text-gray-900">Agregar Nuevo Paciente</h2>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Nombre completo */}
                    <div>
                        <InputLabel htmlFor="name" value="Nombre Completo *" />
                        <TextInput
                            id="name"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            className="mt-1 block w-full"
                            placeholder="Ej: Juan Pérez"
                            required
                        />
                        {errors.name && <InputError message={errors.name} className="mt-2" />}
                    </div>

                    {/* Email */}
                    <div>
                        <InputLabel htmlFor="email" value="Correo Electrónico *" />
                        <TextInput
                            id="email"
                            name="email"
                            type="email"
                            value={formData.email}
                            onChange={handleChange}
                            className="mt-1 block w-full"
                            placeholder="ejemplo@correo.com"
                            required
                        />
                        {errors.email && <InputError message={errors.email} className="mt-2" />}
                    </div>

                    {/* Teléfono */}
                    <div>
                        <InputLabel htmlFor="telefono" value="Teléfono (10 dígitos) *" />
                        <TextInput
                            id="telefono"
                            name="telefono"
                            type="tel"
                            value={formData.telefono}
                            onChange={handleChange}
                            className="mt-1 block w-full"
                            placeholder="1234567890"
                            maxLength="10"
                            required
                        />
                        {errors['contacto.telefono'] && <InputError message={errors['contacto.telefono']} className="mt-2" />}
                    </div>

                    {/* Contraseña */}
                    <div>
                        <InputLabel htmlFor="password" value="Contraseña (opcional)" />
                        <TextInput
                            id="password"
                            name="password"
                            type="password"
                            value={formData.password}
                            onChange={handleChange}
                            className="mt-1 block w-full"
                            placeholder="Si está vacío, se usará el teléfono"
                        />
                        <p className="mt-1 text-sm text-gray-500">
                            Si no se proporciona, se usará el número de teléfono como contraseña
                        </p>
                        {errors.password && <InputError message={errors.password} className="mt-2" />}
                    </div>

                    {/* Asignar psicólogo */}
                    <div>
                        <InputLabel htmlFor="psychologist_id" value="Asignar a Psicólogo (opcional)" />
                        <select
                            id="psychologist_id"
                            name="psychologist_id"
                            value={formData.psychologist_id}
                            onChange={handleChange}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            <option value="">-- Seleccionar psicólogo --</option>
                            {psychologists.map(psychologist => (
                                <option key={psychologist.id} value={psychologist.id}>
                                    {psychologist.name} - {psychologist.email}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-sm text-gray-500">
                            Puedes asignar un psicólogo ahora o hacerlo más tarde
                        </p>
                        {errors.psychologist_id && <InputError message={errors.psychologist_id} className="mt-2" />}
                    </div>

                    {/* Estado activo */}
                    <div className="flex items-center">
                        <input
                            id="activo"
                            name="activo"
                            type="checkbox"
                            checked={formData.activo}
                            onChange={handleChange}
                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label htmlFor="activo" className="ml-2 block text-sm text-gray-900">
                            Paciente activo
                        </label>
                    </div>

                    {/* Botones */}
                    <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                        <SecondaryButton type="button" onClick={handleClose}>
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={loading}>
                            {loading ? (
                                <span className="flex items-center gap-2">
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Creando...
                                </span>
                            ) : (
                                'Crear Paciente'
                            )}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
