import { useState, useEffect } from 'react';
import axios from 'axios';
import DataTable from 'react-data-table-component';
import toast from 'react-hot-toast';
import ModalCita from '../../../components/ModalCita';

export default function AppointmentsTab({ patientId }) {
    const [appointments, setAppointments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [selectedAppointment, setSelectedAppointment] = useState(null);
    const [stats, setStats] = useState(null);

    const fetchAppointments = async () => {
        try {
            setLoading(true);
            const response = await axios.get(`/admin/api/pacientes/${patientId}/citas`);
            setAppointments(response.data.data || []);
        } catch (error) {
            console.error('Error al cargar citas:', error);
            toast.error('Error al cargar las citas');
        } finally {
            setLoading(false);
        }
    };

    const fetchStats = async () => {
        try {
            const response = await axios.get(`/admin/api/pacientes/${patientId}/citas/stats`);
            setStats(response.data);
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
        }
    };

    useEffect(() => {
        fetchAppointments();
        fetchStats();
    }, [patientId]);

    const handleNewAppointment = () => {
        setSelectedAppointment(null);
        setShowModal(true);
    };

    const handleEditAppointment = (appointment) => {
        setSelectedAppointment(appointment);
        setShowModal(true);
    };

    const handleDeleteAppointment = async (appointmentId) => {
        if (!confirm('¿Estás seguro de que deseas cancelar esta cita?')) {
            return;
        }

        try {
            await axios.delete(`/admin/api/citas/${appointmentId}`);
            toast.success('Cita cancelada exitosamente');
            fetchAppointments();
            fetchStats();
        } catch (error) {
            console.error('Error al cancelar cita:', error);
            toast.error(error.response?.data?.message || 'Error al cancelar la cita');
        }
    };

    const handleModalClose = (refresh = false) => {
        setShowModal(false);
        setSelectedAppointment(null);
        if (refresh) {
            fetchAppointments();
            fetchStats();
        }
    };

    const getStateBadgeClass = (state) => {
        const classes = {
            'Creado': 'bg-blue-100 text-blue-800',
            'programada': 'bg-blue-100 text-blue-800',
            'Confirmado': 'bg-green-100 text-green-800',
            'confirmada': 'bg-green-100 text-green-800',
            'Completado': 'bg-gray-100 text-gray-800',
            'completada': 'bg-gray-100 text-gray-800',
            'Cancelado': 'bg-red-100 text-red-800',
            'cancelada': 'bg-red-100 text-red-800',
            'No asistió': 'bg-yellow-100 text-yellow-800',
            'no_asistio': 'bg-yellow-100 text-yellow-800'
        };
        return classes[state] || 'bg-gray-100 text-gray-800';
    };

    const getTipoBadgeClass = (tipo) => {
        return tipo === 'presencial'
            ? 'bg-purple-100 text-purple-800'
            : 'bg-cyan-100 text-cyan-800';
    };

    const columns = [
        {
            name: 'Fecha',
            selector: row => row.fecha_inicio,
            sortable: true,
            format: row => new Date(row.fecha_inicio).toLocaleString('es-MX', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }),
            width: '180px'
        },
        {
            name: 'Psicólogo',
            selector: row => row.user?.name || 'N/A',
            sortable: true,
        },
        {
            name: 'Tipo',
            cell: row => (
                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getTipoBadgeClass(row.tipo)}`}>
                    {row.tipo === 'presencial' ? 'Presencial' : 'Virtual'}
                </span>
            ),
            width: '120px'
        },
        {
            name: 'Estado',
            cell: row => (
                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStateBadgeClass(row.state)}`}>
                    {row.state === 'programada' ? 'Programada' :
                        row.state === 'confirmada' ? 'Confirmada' :
                            row.state === 'completada' ? 'Completada' :
                                row.state === 'cancelada' ? 'Cancelada' :
                                    row.state === 'no_asistio' ? 'No Asistió' : row.state}
                </span>
            ),
            width: '130px'
        },
        {
            name: 'Duración',
            selector: row => {
                const inicio = new Date(row.fecha_inicio);
                const fin = new Date(row.fecha_fin);
                const diff = (fin - inicio) / (1000 * 60);
                return `${diff} min`;
            },
            width: '100px'
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex space-x-2">
                    {!['cancelada', 'Cancelado', 'completada', 'Completado'].includes(row.state) && (
                        <>
                            <button
                                onClick={() => handleEditAppointment(row)}
                                className="text-indigo-600 hover:text-indigo-900"
                                title="Editar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                            <button
                                onClick={() => handleDeleteAppointment(row.id)}
                                className="text-red-600 hover:text-red-900"
                                title="Cancelar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
                                </svg>
                            </button>
                        </>
                    )}
                </div>
            ),
            width: '100px'
        }
    ];

    return (
        <div>
            {/* Estadísticas */}
            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <p className="text-sm text-gray-600">Total de Citas</p>
                        <p className="text-2xl font-bold text-blue-600">{stats.total}</p>
                    </div>
                    <div className="bg-green-50 p-4 rounded-lg">
                        <p className="text-sm text-gray-600">Completadas</p>
                        <p className="text-2xl font-bold text-green-600">{stats.completadas}</p>
                    </div>
                    <div className="bg-yellow-50 p-4 rounded-lg">
                        <p className="text-sm text-gray-600">Pendientes</p>
                        <p className="text-2xl font-bold text-yellow-600">{stats.pendientes}</p>
                    </div>
                    <div className="bg-red-50 p-4 rounded-lg">
                        <p className="text-sm text-gray-600">Canceladas</p>
                        <p className="text-2xl font-bold text-red-600">{stats.canceladas}</p>
                    </div>
                </div>
            )}

            {/* Botón Nueva Cita */}
            <div className="mb-4">
                <button
                    onClick={handleNewAppointment}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded"
                >
                    + Nueva Cita
                </button>
            </div>

            {/* Tabla de Citas */}
            <DataTable
                columns={columns}
                data={appointments}
                progressPending={loading}
                pagination
                paginationPerPage={10}
                paginationRowsPerPageOptions={[10, 20, 30, 50]}
                noDataComponent="No hay citas registradas"
                highlightOnHover
                pointerOnHover
            />

            {/* Modal para crear/editar cita */}
            {showModal && (
                <ModalCita
                    show={showModal}
                    onClose={handleModalClose}
                    patientId={patientId}
                    appointment={selectedAppointment}
                />
            )}
        </div>
    );
}
