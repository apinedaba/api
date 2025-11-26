import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';
export default function Carts({ auth, carts, status }) {
    console.log(carts);

    const columns = [
        {
            name: 'ID',
            selector: row => row?.id,
            cell: row => <a href={`/cart/${row?.id}`} >{row?.id}</a>
        },
        {
            name: 'Psicologo',
            selector: row => row?.user?.name,
            cell: row => <a href={`/psicologo/${row?.user?.id}`} >{row?.user?.name}</a>
        },
        {
            name: 'Paciente',
            selector: row => row?.patient?.name,
            cell: row => <a href={`/paciente/${row?.patient?.id}`} >{row?.patient?.name}</a>
        },
        {
            name: 'Estatus',
            cell: row => {
                if (row?.estado === 'pendiente') {
                    return <span className="bg-orange-600 text-white px-2 py-1 rounded-full">Pendiente</span>
                } else if (row?.estado === 'pendientePago') {
                    return <span className="bg-yellow-600 text-white px-2 py-1 rounded-full">Pendiente Pago</span>
                } else {
                    return <span className="bg-green-600 text-white px-2 py-1 rounded-full">Pagado</span>
                }
            }
        },
        {
            name: 'Fecha',
            selector: row => row?.fecha,
            cell: row => <a href={`/cart/${row?.id}`} >{row?.fecha}</a>
        },
        {
            name: 'Hora',
            selector: row => row?.hora,
            cell: row => <a href={`/cart/${row?.id}`} >{row?.hora}</a>
        },
        {
            name: 'Duracion',
            selector: row => row?.duracion,
            cell: row => <a href={`/cart/${row?.id}`} >{row?.duracion}</a>
        },
        {
            name: 'Precio',
            selector: row => row?.precio,
            cell: row => <a href={`/cart/${row?.id}`} >{row?.precio}</a>
        },
        {
            name: 'Intento de pago',
            selector: row => row?.payment_intent_id,
            cell: row => {
                if (row?.payment_intent_id) {
                    return <span className="bg-green-600 text-white px-2 py-1 rounded-full">Si</span>
                } else {
                    return <span className="bg-red-600 text-white px-2 py-1 rounded-full">No</span>
                }
            }
        },
    ];
    const [filter, setFilter] = useState(false);
    const [resetPaginationToggle, setResetPaginationToggle] = useState(false);
    const filteredItems = filter?.name ? carts?.filter(
        item => item.name && item.name.toLowerCase().includes(filter?.name?.toLowerCase()),
    ) : carts;

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
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Psicologos</h2>}
        >
            <Head title="Psicologos" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">Lista de psicologos</div>
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
