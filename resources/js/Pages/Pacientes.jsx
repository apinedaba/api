import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';
import ModalAgregarPaciente from '@/Components/Pacientes/ModalAgregarPaciente';
import ModalAsignarPsicologo from '@/Components/Pacientes/ModalAsignarPsicologo';
import axios from 'axios';
import { toast, Toaster } from 'react-hot-toast';

export default function Pacientes({ auth, pacientes, status }) {
    const [patients, setPatients] = useState(pacientes || []);
    const [showModalAgregar, setShowModalAgregar] = useState(false);
    const [showModalAsignar, setShowModalAsignar] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleAgregarPaciente = () => {
        setShowModalAgregar(true);
    };

    const handleAsignarPsicologo = (patient) => {
        setSelectedPatient(patient);
        setShowModalAsignar(true);
    };

    const refreshPatients = () => {
        setLoading(true);
        router.reload({
            only: ['pacientes'],
            onFinish: () => {
                setLoading(false);
                toast.success('Lista actualizada');
            }
        });
    };

    const columns = [
        {
            name: 'ID',
            selector: row => row?.id,
            cell: row => <a href={`/paciente/${row?.id}`} className="text-blue-600 hover:underline">{row?.id}</a>,
            width: '80px'
        },
        {
            name: 'Nombre',
            selector: row => row?.name,
            cell: row => (
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center text-sm font-semibold">
                        {row?.name?.charAt(0).toUpperCase()}
                    </div>
                    <span>{row?.name}</span>
                </div>
            ),
            sortable: true,
            grow: 2
        },
        {
            name: 'Email',
            selector: row => row?.email,
            cell: row => row?.email,
            sortable: true
        },
        {
            name: 'Teléfono',
            selector: row => getTelefono(row),
            cell: row => getTelefono(row)
        },
        {
            name: 'Psicólogos',
            selector: row => row?.connections?.length || 0,
            cell: row => {
                const psychologists = row?.connections || [];
                const activePsychologist = psychologists.find(conn => conn.activo);

                return (
                    <div className="flex flex-col gap-1">
                        <span className="font-semibold">{psychologists.length} asignado(s)</span>
                        {activePsychologist && (
                            <span className="text-xs text-green-600">
                                Principal: {activePsychologist.user?.name}
                            </span>
                        )}
                    </div>
                );
            },
            width: '180px'
        },
        {
            name: 'Estado',
            selector: row => row?.activo,
            cell: row => (
                <span className={`px-2 py-1 rounded-full text-xs font-semibold ${row?.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                    }`}>
                    {row?.activo ? 'Activo' : 'Inactivo'}
                </span>
            ),
            width: '100px'
        },
        {
            name: 'Acciones',
            cell: row => (
                <div className="flex gap-2">
                    <button
                        onClick={() => handleAsignarPsicologo(row)}
                        className="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
                        title="Asignar psicólogo"
                    >
                        Asignar
                    </button>
                    <a
                        href={`/paciente/${row?.id}`}
                        className="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm"
                    >
                        Ver
                    </a>
                </div>
            ),
            width: '150px'
        }
    ];
    const [filter, setFilter] = useState(false);
    const [resetPaginationToggle, setResetPaginationToggle] = useState(false);
    const filteredItems = filter?.name ? pacientes?.filter(
        item => item.name && item.name.toLowerCase().includes(filter?.name?.toLowerCase()),
    ) : pacientes;

    const subHeaderComponentMemo = useMemo(() => {
        const handleClear = () => {
            if (filter) {
                setResetPaginationToggle(!resetPaginationToggle);
                setFilter({});
            }
        };

        return (
            <FilterComponent onFilter={setFilter} onClear={handleClear} filters={filter} />
        );
    }, [filter, resetPaginationToggle]);
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">Pacientes</h2>
                    <button
                        onClick={handleAgregarPaciente}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar Paciente
                    </button>
                </div>
            }
        >
            <Head title="Pacientes" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Lista de pacientes ({patients?.length || 0})
                                </h3>
                                <button
                                    onClick={refreshPatients}
                                    disabled={loading}
                                    className="px-3 py-1 text-sm text-gray-600 hover:text-gray-900 flex items-center gap-1"
                                >
                                    <svg className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Actualizar
                                </button>
                            </div>
                            <DataTable
                                columns={columns}
                                data={filter?.onlyActives ? filteredItems?.filter(item => item?.activo) : filteredItems}
                                pagination
                                paginationPerPage={15}
                                paginationRowsPerPageOptions={[10, 15, 25, 50]}
                                subHeader
                                subHeaderComponent={subHeaderComponentMemo}
                                persistTableHead
                                highlightOnHover
                                pointerOnHover
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Modales */}
            <Toaster position="top-right" />

            {showModalAgregar && (
                <ModalAgregarPaciente
                    show={showModalAgregar}
                    onClose={() => setShowModalAgregar(false)}
                    onSuccess={refreshPatients}
                />
            )}

            {showModalAsignar && selectedPatient && (
                <ModalAsignarPsicologo
                    show={showModalAsignar}
                    patient={selectedPatient}
                    onClose={() => {
                        setShowModalAsignar(false);
                        setSelectedPatient(null);
                    }}
                    onSuccess={refreshPatients}
                />
            )}
        </AuthenticatedLayout>
    );
}


const FilterComponent = ({ filter, onFilter, onClear }) => (
    <div className='grid grid-cols-2 w-full gap-4'>
        <div className='flex justify-start items-center'>
            <label htmlFor="onLyActives" className={`flex items-center gap-2 cursor-pointer border border-gray-300 rounded-md p-2 ${filter?.onlyActives ? 'bg-gray-100' : ''}`}>
                Ver Solo Activos
                <input type="checkbox" name='OnlyActives' className='hidden' id='onLyActives' onChange={() => onFilter((filter) => ({ ...filter, onlyActives: !filter?.onlyActives }))} checked={filter?.onlyActives} />
            </label>
        </div>
        <div className='relative overflow-hidden'>
            <input
                id="search"
                type="text"
                placeholder="Buscar por nombre"
                aria-label="Search Input"
                value={filter?.name}
                onChange={(event) => onFilter((prev) => ({ ...prev, name: event.target.value }))}
                className='border border-gray-300 rounded-md p-2'
            />
            <button type="button" onClick={onClear} className='bg-red-500 text-white px-2 py-2 absolute top-0 right-0 h-full rounded-r-md'>
                X
            </button>
        </div>
    </div>
);


function getTelefono(paciente) {
    const telefono = paciente?.contacto?.telefono;
    if (typeof paciente?.contacto === 'object' && paciente?.contacto) {
        return telefono;
    } else {
        return JSON.parse(paciente?.contacto)?.telefono;
    }
}