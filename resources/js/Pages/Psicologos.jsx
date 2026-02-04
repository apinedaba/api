import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataTable from 'react-data-table-component';
import styled from 'styled-components';
import FotoPerfil from '@/Components/FotoPerfil';
export default function Dashboard({ auth, psicologos, status }) {
  function limpiarTelefono(telefono) {
    return telefono?.replace(/[()\-\s+]/g, '');
  }
  const columns = [
    {
      name: 'Foto',
      cell: row => (<FotoPerfil image={row?.image || null} name={row?.name} className="w-12 h-12 rounded-full my-2" alt={row?.name} />)
    },
    {
      name: 'Profesional',
      cell: row => (<a href={`/psicologo/${row?.id}`}>{row?.name}</a>)

    },
    {
      name: 'Estatus',
      cell: row => (
        <span className={`${row?.identity_verification_status === "approved" ? "bg-green-700" : row?.identity_verification_status === "sended" ? "bg-yellow-600" : "bg-red-600"} text-white px-2 py-1 rounded-full`}>
          {row?.identity_verification_status === "approved" ? "Aprobado" : row?.identity_verification_status === "sended" ? "Enviado" : "Pendiente"}
        </span>)
    },
    {
      name: 'Suscripcion',
      cell: row => (
        <span className={`
          ${row?.subscription?.stripe_status === "active" && "bg-green-700"} 
          ${(row?.subscription?.stripe_status === "trialing" || row?.subscription?.stripe_status === "trial") && "bg-yellow-600"}
          ${row?.subscription?.stripe_status === "canceled" && "bg-red-600"}
          ${row?.subscription?.stripe_status === "past_due" && "bg-red-400"}
          ${row?.subscription?.stripe_status === "trial_expired" && "bg-orange-600"}
          ${row?.has_lifetime_access && "bg-blue-600"}
          text-white px-2 py-1 rounded-full`}
        >
          {
            row?.subscription?.stripe_status === "active" && "Activo"
          }
          {
            (!row?.has_lifetime_access && !row?.subscription?.id) && "Sin suscripcion"
          }
          {
            (row?.subscription?.stripe_status === "trialing" || row?.subscription?.stripe_status === "trial") && "Prueba"
          }
          {
            (row?.subscription?.stripe_status === "trial_expired") && "Prueba Expirada"
          }
          {
            row?.subscription?.stripe_status === "canceled" && "Cancelado"
          }
          {
            row?.has_lifetime_access && "Permanente"
          }
          {
            row?.subscription?.stripe_status === "past_due" && "Vencido"
          }

        </span>)
    },
    {
      name: 'Correo',
      selector: row => row?.email,
    },
    {
      name: 'Telefono',
      cell: row => (
        <Link href={`https://wa.me/${limpiarTelefono(row?.contacto?.whatsapp)}`} target="_blank">
          {row?.contacto?.whatsapp || ""}
        </Link>
      ),
    },
    {
      name: 'Estado',
      selector: row => row?.address?.estado || "",
    },
  ];
  const [filter, setFilter] = useState(false);
  const [resetPaginationToggle, setResetPaginationToggle] = useState(false);
  const filteredItems = filter?.name ? psicologos?.filter(
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
              data={filter?.onlyActives ? filteredItems?.filter(item => item?.identity_verification_status === "approved") : filteredItems}
              pagination
              paginationPerPage={10}
              paginationComponentOptions
              subHeader
              subHeaderComponent={subHeaderComponentMemo}
              persistTableHead
              responsive
            />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}


const FilterComponent = ({ filters, onFilter, onClear }) => (
  <div className='grid grid-cols-2 w-full gap-4'>
    {console.log(filters)}
    <div className='flex items-center gap-2'>
      <label htmlFor="onLyActives" className={`flex items-center gap-2 cursor-pointer border border-gray-300 px-2 py-1 rounded ${filters?.onlyActives ? 'text-green-600 border-green-600' : 'text-gray-600'}`}>
        Ver Solo Activos
        <input type="checkbox" className='ml-2 hidden' name='OnlyActives' id='onLyActives' onChange={() => onFilter((filter) => ({ ...filter, onlyActives: !filters?.onlyActives }))} checked={filters?.onlyActives} />
      </label>
    </div>
    <div className='flex items-end gap-2 flex-col'>
      <span className='text-sm text-gray-600 mb-0'>Buscar por nombre</span>
      <input
        id="search"
        type="text"
        placeholder="Buscar por nombre"
        aria-label="Search Input"
        value={filters?.name}
        className='border border-gray-300 px-2 py-1 rounded'
        onChange={(event) => onFilter((prev) => ({ ...prev, name: event.target.value }))}
      />
      <button type="button" onClick={onClear}>

      </button>
    </div>
  </div>
);
