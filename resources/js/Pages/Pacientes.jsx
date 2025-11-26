import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';

export default function Pacientes({ auth, pacientes, status }) {
    console.log('pacientes', pacientes);

    const columns = [
        {
            name: 'ID',
            selector: row => row?.id,
            cell: row => <a href={`/paciente/${row?.id}`} >{row?.id}</a>
        },
        {
            name: 'nombre',
            selector: row => row?.name,
            cell: row => row?.name
        },
        {
            name: 'activo',
            selector: row => row?.activo,
            cell: row => row?.activo
        },
        {
            name: 'telefono',
            selector: row => getTelefono(row),
            cell: row => getTelefono(row)
        },
        {
            name: 'email',
            selector: row => row?.email,
            cell: row => row?.email
        },
        {
            name: 'enlaces',
            selector: row => row?.connections?.length,
            cell: row => row?.connections?.length
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
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Pacientes</h2>}
        >
            <Head title="Pacientes" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">Lista de pacientes</div>
                        <DataTable
                            columns={columns}
                            data={filter?.onlyActives ? filteredItems?.filter(item => item?.activo) : filteredItems}
                            pagination
                            paginationPerPage={10}
                            paginationComponentOptions
                            subHeader
                            subHeaderComponent={subHeaderComponentMemo}
                            persistTableHead
                        />
                    </div>
                </div>
            </div>
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