import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function ClinicShow({ auth, clinic, metrics, appointments, psychologists }) {
    const { data, setData, put, processing, errors } = useForm({
        name: clinic.name || '',
        owner_user_id: clinic.owner?.id || '',
        description: clinic.description || '',
        status: clinic.status || 'active',
        base_psychologist_limit: clinic.base_psychologist_limit || 5,
        addon_psychologist_slots: clinic.addon_psychologist_slots || 0,
        contact: clinic.contact || {},
        settings: clinic.settings || {},
    });

    const addMemberForm = useForm({
        user_id: '',
        role: 'psychologist',
        is_primary: false,
        can_manage_schedule: false,
        can_manage_patients: false,
        can_view_finance: false,
    });

    const [filters, setFilters] = useState({
        professional: '',
        state: '',
        date: '',
    });

    const submitClinic = (event) => {
        event.preventDefault();
        put(route('clinics.update', clinic.id));
    };

    const submitMember = (event) => {
        event.preventDefault();
        addMemberForm.post(route('clinics.psychologists.attach', clinic.id), {
            preserveScroll: true,
            onSuccess: () => addMemberForm.reset('user_id', 'role', 'is_primary', 'can_manage_schedule', 'can_manage_patients', 'can_view_finance'),
        });
    };

    const filteredAppointments = useMemo(() => {
        return appointments.filter((appointment) => {
            const matchesProfessional = !filters.professional || appointment.professional === filters.professional;
            const matchesState = !filters.state || appointment.state === filters.state;
            const matchesDate = !filters.date || appointment.start?.slice(0, 10) === filters.date;

            return matchesProfessional && matchesState && matchesDate;
        });
    }, [appointments, filters]);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">{clinic.slug}</p>
                        <h2 className="text-2xl font-semibold leading-tight text-slate-900">{clinic.name}</h2>
                    </div>
                    <Link href={route('clinics.index')} className="text-sm font-semibold text-sky-600 hover:text-sky-700">
                        Volver a clinicas
                    </Link>
                </div>
            }
        >
            <Head title={clinic.name} />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Psicologos" value={metrics.psychologists} helper="Miembros operando en esta clinica" />
                        <MetricCard label="Pacientes vinculados" value={metrics.patients} helper="Pacientes relacionados a su equipo" />
                        <MetricCard label="Citas hoy" value={metrics.appointments_today} helper="Agenda cruzada del dia" />
                        <MetricCard label="Proximas citas" value={metrics.appointments_upcoming} helper="Sesiones futuras visibles para clinica" />
                    </section>

                    {addMemberForm.errors.clinic_capacity ? (
                        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                            {addMemberForm.errors.clinic_capacity}
                        </div>
                    ) : null}

                    <section className="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-slate-900">Configuracion de la clinica</h3>
                            <form onSubmit={submitClinic} className="mt-5 grid gap-4 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <InputLabel value="Nombre" />
                                    <TextInput value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 block w-full" />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Owner" />
                                    <TextInput value={clinic.owner ? `${clinic.owner.name} · ${clinic.owner.email}` : 'Sin owner'} disabled className="mt-1 block w-full bg-slate-50" />
                                </div>

                                <div>
                                    <InputLabel value="Estatus" />
                                    <select
                                        value={data.status}
                                        onChange={(event) => setData('status', event.target.value)}
                                        className="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                    >
                                        <option value="active">Activa</option>
                                        <option value="draft">Borrador</option>
                                        <option value="paused">Pausada</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel value="Psicologos incluidos" />
                                    <TextInput
                                        type="number"
                                        min="1"
                                        value={data.base_psychologist_limit}
                                        onChange={(event) => setData('base_psychologist_limit', event.target.value)}
                                        className="mt-1 block w-full"
                                    />
                                    <InputError message={errors.base_psychologist_limit} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Addons de espacios" />
                                    <TextInput
                                        type="number"
                                        min="0"
                                        value={data.addon_psychologist_slots}
                                        onChange={(event) => setData('addon_psychologist_slots', event.target.value)}
                                        className="mt-1 block w-full"
                                    />
                                    <InputError message={errors.addon_psychologist_slots} className="mt-2" />
                                </div>

                                <div className="md:col-span-2">
                                    <InputLabel value="Descripcion" />
                                    <textarea
                                        rows={4}
                                        value={data.description}
                                        onChange={(event) => setData('description', event.target.value)}
                                        className="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                    />
                                </div>

                                <div className="md:col-span-2">
                                    <div className="mb-4 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-slate-700">
                                        <strong className="text-slate-900">Capacidad actual:</strong> {clinic.capacity_used} de {clinic.capacity_total} espacios ocupados.
                                        Quedan {clinic.capacity_remaining} disponibles para nuevos psicologos.
                                    </div>
                                    <PrimaryButton disabled={processing}>{processing ? 'Guardando...' : 'Guardar cambios'}</PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-slate-900">Agregar psicologo</h3>
                            <form onSubmit={submitMember} className="mt-5 space-y-4">
                                <div>
                                    <InputLabel value="Profesional" />
                                    <select
                                        value={addMemberForm.data.user_id}
                                        onChange={(event) => addMemberForm.setData('user_id', event.target.value)}
                                        className="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                    >
                                        <option value="">Selecciona un psicologo</option>
                                        {psychologists.map((professional) => (
                                            <option key={professional.id} value={professional.id}>
                                                {professional.name} · {professional.email}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={addMemberForm.errors.user_id} className="mt-2" />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel value="Rol" />
                                        <select
                                            value={addMemberForm.data.role}
                                            onChange={(event) => addMemberForm.setData('role', event.target.value)}
                                            className="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                        >
                                        <option value="psychologist">Psicologo</option>
                                        <option value="manager">Manager</option>
                                        <option value="assistant">Asistente</option>
                                        </select>
                                    </div>

                                    <label className="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={addMemberForm.data.is_primary}
                                            onChange={(event) => addMemberForm.setData('is_primary', event.target.checked)}
                                        />
                                        Marcar como clinica primaria
                                    </label>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    <PermissionCheckbox
                                        checked={addMemberForm.data.can_manage_schedule}
                                        onChange={(checked) => addMemberForm.setData('can_manage_schedule', checked)}
                                        label="Gestionar agenda"
                                    />
                                    <PermissionCheckbox
                                        checked={addMemberForm.data.can_manage_patients}
                                        onChange={(checked) => addMemberForm.setData('can_manage_patients', checked)}
                                        label="Gestionar pacientes"
                                    />
                                    <PermissionCheckbox
                                        checked={addMemberForm.data.can_view_finance}
                                        onChange={(checked) => addMemberForm.setData('can_view_finance', checked)}
                                        label="Ver finanzas"
                                    />
                                </div>

                                <PrimaryButton disabled={addMemberForm.processing}>
                                    {addMemberForm.processing ? 'Vinculando...' : 'Vincular profesional'}
                                </PrimaryButton>
                                <p className="text-xs text-slate-500">
                                    Los psicologos independientes no entran automaticamente aqui. Solo se vuelven parte de una clinica si se les asigna una membresia.
                                </p>
                            </form>
                        </div>
                    </section>

                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">Equipo clinico</h3>
                                <p className="text-sm text-slate-500">Cada profesional mantiene su espacio, pero la clinica puede leer y operar el conjunto.</p>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                            {clinic.memberships.map((membership) => (
                                <article key={membership.id} className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <div className="flex items-start gap-4">
                                        <FotoPerfil image={membership.user?.image} name={membership.user?.name} className="h-14 w-14 rounded-full" />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="truncate text-lg font-semibold text-slate-900">{membership.user?.name}</h4>
                                                {membership.is_primary ? (
                                                    <span className="rounded-full bg-sky-100 px-2 py-1 text-[11px] font-bold uppercase tracking-[0.15em] text-sky-700">
                                                        primaria
                                                    </span>
                                                ) : null}
                                            </div>
                                            <p className="truncate text-sm text-slate-500">{membership.user?.email}</p>
                                            <p className="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{membership.role}</p>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                                        {membership.can_manage_schedule ? <Tag>Pueden operar agenda</Tag> : null}
                                        {membership.can_manage_patients ? <Tag>Pueden operar pacientes</Tag> : null}
                                        {membership.can_view_finance ? <Tag>Ven finanzas</Tag> : null}
                                    </div>

                                    <div className="mt-5 flex items-center justify-between">
                                        <Link href={route('psicologoShow', membership.user_id)} className="text-sm font-semibold text-sky-600 hover:text-sky-700">
                                            Ver perfil
                                        </Link>
                                        <button
                                            type="button"
                                            className="text-sm font-semibold text-red-600 hover:text-red-700"
                                            onClick={() => {
                                                if (window.confirm('¿Seguro que quieres remover a este profesional de la clinica?')) {
                                                    router.delete(route('clinics.psychologists.detach', [clinic.id, membership.user_id]), { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            Remover
                                        </button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">Agenda mezclada de la clinica</h3>
                                <p className="text-sm text-slate-500">Vista combinada de citas de todos los psicologos vinculados a esta estructura.</p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <select
                                    value={filters.professional}
                                    onChange={(event) => setFilters((prev) => ({ ...prev, professional: event.target.value }))}
                                    className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                >
                                    <option value="">Todos los psicologos</option>
                                    {clinic.memberships.map((membership) => (
                                        <option key={membership.id} value={membership.user?.name}>
                                            {membership.user?.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filters.state}
                                    onChange={(event) => setFilters((prev) => ({ ...prev, state: event.target.value }))}
                                    className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                >
                                    <option value="">Todos los estados</option>
                                    {[...new Set(appointments.map((appointment) => appointment.state).filter(Boolean))].map((state) => (
                                        <option key={state} value={state}>{state}</option>
                                    ))}
                                </select>
                                <input
                                    type="date"
                                    value={filters.date}
                                    onChange={(event) => setFilters((prev) => ({ ...prev, date: event.target.value }))}
                                    className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <div className="mt-6 overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Psicologo</TableHead>
                                        <TableHead>Paciente</TableHead>
                                        <TableHead>Modalidad</TableHead>
                                        <TableHead>Estatus</TableHead>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {filteredAppointments.map((appointment) => (
                                        <tr key={appointment.id}>
                                            <TableCell>{formatDateRange(appointment.start, appointment.end)}</TableCell>
                                            <TableCell>{appointment.professional || 'Sin profesional'}</TableCell>
                                            <TableCell>
                                                <div className="font-medium text-slate-900">{appointment.patient || 'Sin paciente'}</div>
                                                <div className="text-xs text-slate-500">{appointment.patient_email || appointment.patient_phone || ''}</div>
                                            </TableCell>
                                            <TableCell>{appointment.extendedProps?.formato || appointment.extendedProps?.tipoSesion || 'No definido'}</TableCell>
                                            <TableCell>
                                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                    {appointment.state || 'Sin estado'}
                                                </span>
                                            </TableCell>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function MetricCard({ label, value, helper }) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">{label}</p>
            <p className="mt-3 text-4xl font-black tracking-tight text-slate-900">{value}</p>
            <p className="mt-2 text-sm text-slate-500">{helper}</p>
        </article>
    );
}

function PermissionCheckbox({ checked, onChange, label }) {
    return (
        <label className="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            {label}
        </label>
    );
}

function Tag({ children }) {
    return <span className="rounded-full bg-white px-3 py-1 shadow-sm">{children}</span>;
}

function TableHead({ children }) {
    return <th className="px-4 py-3 text-left font-semibold text-slate-600">{children}</th>;
}

function TableCell({ children }) {
    return <td className="px-4 py-4 align-top text-slate-700">{children}</td>;
}

function formatDateRange(start, end) {
    if (!start) {
        return 'Sin fecha';
    }

    const startDate = new Date(start);
    const endDate = end ? new Date(end) : null;

    return `${startDate.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })} · ${startDate.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}${endDate ? ` - ${endDate.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}` : ''}`;
}
