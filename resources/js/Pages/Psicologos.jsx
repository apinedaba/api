import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';
import styled from 'styled-components';

export default function Dashboard({ auth, psicologos, status }) {

  const columns = [
    {
      name: 'Profesional',
      cell: row => (<a href={`/psicologo/${row?.id}`}>{row?.name}</a>)    
      
    },
    {
      name: 'Correo',
      selector: row => row?.email,
    },
    {
      name: 'Telefono',
      selector: row => row?.contacto?.telefono || "",
    },
    {
      name: 'Pais',
      selector: row => row?.email,
    },
    {
      name: 'Estado',
      selector: row => row?.address?.pais || "",
    },
    {
      name: 'Correo',
      selector: row => row?.address?.estado || "",
    },
    {
      name: 'Estatus',
      cell: row => (<span className={`${row?.activo ? "bg-green-700" : "bg-red-600"} text-white px-2 py-1 rounded-full`}>{row?.activo ? "Activo" : "Inactivo"}</span>)        },
  ];
  const [filter, setFilter] = useState(false);
  const [resetPaginationToggle, setResetPaginationToggle] = useState(false);
  const filteredItems = filter?.name  ? psicologos?.filter(
    item => item.name && item.name.toLowerCase().includes(filter?.name?.toLowerCase()),
  ) : psicologos;

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
  <div className='grid grid-cols-2 bg-red-600 w-full gap-4'>
    <div>
      <label htmlFor="onLyActives">
        Ver Solo Activos
        <input type="checkbox" name='OnlyActives' id='onLyActives' onChange={() => onFilter((filter) =>({...filter, onlyActives: !filter?.onlyActives}))} checked={filter?.onlyActives}/>
      </label>
    </div>
    <div>
      <span>Buscar</span>
      <input
        id="search"
        type="text"
        placeholder="Buscar por nombre"
        aria-label="Search Input"
        value={filter?.name}
        onChange={(event) => onFilter((prev) => ({...prev, name: event.target.value}))}
      />
      <button type="button" onClick={onClear}>
        X
      </button>
    </div>
  </div>
);
