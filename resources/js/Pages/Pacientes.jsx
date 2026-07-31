import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ModalAsignarPsicologo from '@/Components/Pacientes/ModalAsignarPsicologo';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast, Toaster } from 'react-hot-toast';

const statusOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'active', label: 'Activos' },
    { value: 'inactive', label: 'Inactivos' },
    { value: 'unassigned', label: 'Sin psicólogo' },
];

export default function Pacientes({ auth, pacientes = [] }) {
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [selectedPatient, setSelectedPatient] = useState(null);
    const [loading, setLoading] = useState(false);

    const summary = useMemo(() => ({
        total: pacientes.length,
        active: pacientes.filter((patient) => patient.activo).length,
        unassigned: pacientes.filter((patient) => !patient.connections?.length).length,
    }), [pacientes]);

    const filteredPatients = useMemo(() => {
        const normalizedQuery = query.trim().toLocaleLowerCase('es');

        return pacientes.filter((patient) => {
            const phone = getPhone(patient);
            const matchesQuery = !normalizedQuery || [patient.name, patient.email, phone, patient.id]
                .some((value) => String(value || '').toLocaleLowerCase('es').includes(normalizedQuery));
            const matchesStatus = statusFilter === 'all'
                || (statusFilter === 'active' && patient.activo)
                || (statusFilter === 'inactive' && !patient.activo)
                || (statusFilter === 'unassigned' && !patient.connections?.length);

            return matchesQuery && matchesStatus;
        });
    }, [pacientes, query, statusFilter]);

    const refreshPatients = () => {
        setLoading(true);
        router.reload({
            only: ['pacientes'],
            preserveScroll: true,
            onSuccess: () => toast.success('Registros actualizados'),
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">Directorio de pacientes</p>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">Registros desde el sitio web</h1>
                    <p className="mt-2 max-w-2xl text-sm text-slate-500">
                        Personas que crearon su propia cuenta en MindMeet. Las altas realizadas por psicólogos no aparecen aquí.
                    </p>
                </div>
            }
        >
            <Head title="Registros web" />
            <Toaster position="top-right" />

            <div className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <section className="grid gap-4 sm:grid-cols-3">
                        <MetricCard label="Registros web" value={summary.total} tone="blue" />
                        <MetricCard label="Cuentas activas" value={summary.active} tone="emerald" />
                        <MetricCard label="Sin psicólogo" value={summary.unassigned} tone="amber" />
                    </section>

                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-4 sm:p-6">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-slate-950">Pacientes registrados</h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {filteredPatients.length} de {pacientes.length} registros
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <label className="relative block sm:w-80">
                                        <span className="sr-only">Buscar pacientes</span>
                                        <svg className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="m21 21-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                        </svg>
                                        <input
                                            value={query}
                                            onChange={(event) => setQuery(event.target.value)}
                                            placeholder="Nombre, correo, teléfono o ID"
                                            className="w-full rounded-xl border-slate-200 py-2.5 pl-11 pr-4 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        onClick={refreshPatients}
                                        disabled={loading}
                                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                                    >
                                        <svg className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M20 12a8 8 0 1 1-2.34-5.66M20 4v6h-6" />
                                        </svg>
                                        Actualizar
                                    </button>
                                </div>
                            </div>

                            <div className="mt-5 flex gap-2 overflow-x-auto pb-1">
                                {statusOptions.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setStatusFilter(option.value)}
                                        className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition ${
                                            statusFilter === option.value
                                                ? 'bg-slate-950 text-white shadow-sm'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {filteredPatients.length ? (
                            <>
                                <div className="hidden overflow-x-auto md:block">
                                    <table className="w-full">
                                        <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">
                                            <tr>
                                                <th className="px-6 py-4">Paciente</th>
                                                <th className="px-6 py-4">Contacto</th>
                                                <th className="px-6 py-4">Psicólogo</th>
                                                <th className="px-6 py-4">Estado</th>
                                                <th className="px-6 py-4">Registro</th>
                                                <th className="px-6 py-4 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {filteredPatients.map((patient) => (
                                                <PatientRow key={patient.id} patient={patient} onAssign={setSelectedPatient} />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="divide-y divide-slate-100 md:hidden">
                                    {filteredPatients.map((patient) => (
                                        <PatientCard key={patient.id} patient={patient} onAssign={setSelectedPatient} />
                                    ))}
                                </div>
                            </>
                        ) : (
                            <EmptyState hasFilters={Boolean(query) || statusFilter !== 'all'} onReset={() => {
                                setQuery('');
                                setStatusFilter('all');
                            }} />
                        )}
                    </section>
                </div>
            </div>

            {selectedPatient ? (
                <ModalAsignarPsicologo
                    show
                    patient={selectedPatient}
                    onClose={() => setSelectedPatient(null)}
                    onSuccess={() => {
                        setSelectedPatient(null);
                        refreshPatients();
                    }}
                />
            ) : null}
        </AuthenticatedLayout>
    );
}

function MetricCard({ label, value, tone }) {
    const tones = {
        blue: 'bg-blue-50 text-blue-700 ring-blue-100',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className={`inline-flex rounded-xl px-3 py-1.5 text-xs font-bold uppercase tracking-wider ring-1 ring-inset ${tones[tone]}`}>
                {label}
            </div>
            <p className="mt-4 text-3xl font-bold tracking-tight text-slate-950">{value.toLocaleString('es-MX')}</p>
        </div>
    );
}

function PatientRow({ patient, onAssign }) {
    const psychologist = getPrimaryPsychologist(patient);

    return (
        <tr className="transition hover:bg-slate-50/80">
            <td className="px-6 py-5"><PatientIdentity patient={patient} /></td>
            <td className="px-6 py-5 text-sm">
                <p className="font-medium text-slate-700">{patient.email || 'Sin correo'}</p>
                <p className="mt-1 text-slate-500">{getPhone(patient) || 'Sin teléfono'}</p>
            </td>
            <td className="px-6 py-5">
                <p className="text-sm font-semibold text-slate-700">{psychologist?.user?.name || 'Sin asignar'}</p>
                {patient.connections?.length > 1 ? <p className="mt-1 text-xs text-slate-500">+{patient.connections.length - 1} adicional(es)</p> : null}
            </td>
            <td className="px-6 py-5"><StatusBadge active={patient.activo} /></td>
            <td className="px-6 py-5 text-sm text-slate-500">{formatDate(patient.registered_at)}</td>
            <td className="px-6 py-5">
                <div className="flex justify-end gap-2">
                    <button type="button" onClick={() => onAssign(patient)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                        Asignar
                    </button>
                    <Link href={`/paciente/${patient.id}`} className="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Ver perfil
                    </Link>
                </div>
            </td>
        </tr>
    );
}

function PatientCard({ patient, onAssign }) {
    const psychologist = getPrimaryPsychologist(patient);

    return (
        <article className="p-5">
            <div className="flex items-start justify-between gap-3">
                <PatientIdentity patient={patient} />
                <StatusBadge active={patient.activo} />
            </div>
            <dl className="mt-5 grid grid-cols-2 gap-4 text-sm">
                <div className="col-span-2">
                    <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Contacto</dt>
                    <dd className="mt-1 break-all font-medium text-slate-700">{patient.email || 'Sin correo'}</dd>
                    <dd className="mt-0.5 text-slate-500">{getPhone(patient) || 'Sin teléfono'}</dd>
                </div>
                <div>
                    <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Psicólogo</dt>
                    <dd className="mt-1 font-medium text-slate-700">{psychologist?.user?.name || 'Sin asignar'}</dd>
                </div>
                <div>
                    <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Registro</dt>
                    <dd className="mt-1 font-medium text-slate-700">{formatDate(patient.registered_at)}</dd>
                </div>
            </dl>
            <div className="mt-5 grid grid-cols-2 gap-2">
                <button type="button" onClick={() => onAssign(patient)} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700">Asignar</button>
                <Link href={`/paciente/${patient.id}`} className="rounded-xl bg-slate-950 px-3 py-2.5 text-center text-sm font-semibold text-white">Ver perfil</Link>
            </div>
        </article>
    );
}

function PatientIdentity({ patient }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-sm font-bold text-white shadow-sm">
                {getInitials(patient.name)}
            </div>
            <div className="min-w-0">
                <p className="truncate font-bold text-slate-900">{patient.name || 'Sin nombre'}</p>
                <p className="mt-0.5 text-xs font-medium text-slate-400">ID #{patient.id}</p>
            </div>
        </div>
    );
}

function StatusBadge({ active }) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ${
            active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
        }`}>
            <span className={`h-1.5 w-1.5 rounded-full ${active ? 'bg-emerald-500' : 'bg-slate-400'}`} />
            {active ? 'Activo' : 'Inactivo'}
        </span>
    );
}

function EmptyState({ hasFilters, onReset }) {
    return (
        <div className="px-6 py-16 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18 0v-2a4 4 0 0 0-3-3.87M13 3.13a4 4 0 0 1 0 7.75M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                </svg>
            </div>
            <h3 className="mt-4 font-bold text-slate-900">{hasFilters ? 'No encontramos coincidencias' : 'Aún no hay registros web'}</h3>
            <p className="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                {hasFilters ? 'Prueba con otro término o limpia los filtros.' : 'Los pacientes que se registren desde el sitio aparecerán aquí.'}
            </p>
            {hasFilters ? <button type="button" onClick={onReset} className="mt-5 text-sm font-bold text-blue-600 hover:text-blue-700">Limpiar filtros</button> : null}
        </div>
    );
}

function getPrimaryPsychologist(patient) {
    return patient.connections?.find((connection) => connection.activo) || patient.connections?.[0];
}

function getPhone(patient) {
    if (patient?.phone) return patient.phone;
    if (patient?.contacto && typeof patient.contacto === 'object') return patient.contacto.telefono || '';

    try {
        return JSON.parse(patient?.contacto || '{}').telefono || '';
    } catch {
        return '';
    }
}

function getInitials(name = '') {
    return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'P';
}

function formatDate(value) {
    if (!value) return 'Sin fecha';

    return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}
