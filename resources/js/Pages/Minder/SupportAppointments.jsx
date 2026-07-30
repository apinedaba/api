import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const days = [
    ['1', 'Lunes'], ['2', 'Martes'], ['3', 'Miércoles'], ['4', 'Jueves'],
    ['5', 'Viernes'], ['6', 'Sábado'], ['7', 'Domingo'],
];

const statusLabels = { pending: 'Pendiente', confirmed: 'Confirmada', cancelled: 'Cancelada', completed: 'Completada' };
const topicLabels = { configuration: 'Configuración', clinic: 'Clínicas y equipo', payments: 'Pagos y suscripción', marketing: 'Marketing y campañas', training: 'Capacitación', other: 'Otro' };
const inputDateTime = (value) => {
    const date = new Date(value);
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

export default function SupportAppointments({ auth, appointments, settings }) {
    const items = appointments?.data ?? [];
    const settingsForm = useForm({
        support_email: settings.support_email,
        duration_minutes: settings.duration_minutes,
        minimum_notice_hours: settings.minimum_notice_hours,
        booking_window_days: settings.booking_window_days,
        weekly_availability: settings.weekly_availability ?? {},
    });
    const [editing, setEditing] = useState(null);
    const appointmentForm = useForm({ status: 'pending', scheduled_at: '', meeting_url: '', admin_notes: '' });

    const toggleDay = (key, enabled) => {
        const next = { ...settingsForm.data.weekly_availability };
        if (enabled) next[key] = next[key]?.length ? next[key] : [{ start: '10:00', end: '17:00' }];
        else delete next[key];
        settingsForm.setData('weekly_availability', next);
    };

    const editAppointment = (appointment) => {
        setEditing(appointment);
        appointmentForm.setData({
            status: appointment.status,
            scheduled_at: inputDateTime(appointment.scheduled_at),
            meeting_url: appointment.meeting_url ?? '',
            admin_notes: appointment.admin_notes ?? '',
        });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800">Sesiones de apoyo MindMeet</h2>}>
            <Head title="Sesiones de apoyo" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex gap-2 border-b border-slate-200">
                        <Link href={route('minder.support.index')} className="px-4 py-2 text-sm text-slate-500">Mensajes</Link>
                        <span className="border-b-2 border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">Sesiones de apoyo</span>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1fr_420px]">
                        <section className="rounded-lg border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 p-5">
                                <h3 className="font-bold text-slate-900">Sesiones solicitadas</h3>
                                <p className="text-sm text-slate-500">Confirma el horario solicitado o propón una nueva fecha.</p>
                            </div>
                            <div className="divide-y divide-slate-100">
                                {items.map((appointment) => (
                                    <button key={appointment.id} type="button" onClick={() => editAppointment(appointment)} className="w-full p-5 text-left hover:bg-slate-50">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold text-slate-900">{appointment.user?.name}</p>
                                                <p className="text-xs text-slate-500">{appointment.user?.email}</p>
                                                <p className="mt-2 text-sm text-slate-700">{topicLabels[appointment.topic] ?? appointment.topic} · {new Date(appointment.scheduled_at).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })}</p>
                                            </div>
                                            <span className={`rounded-full px-2 py-1 text-xs font-semibold ${appointment.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>{statusLabels[appointment.status]}</span>
                                        </div>
                                    </button>
                                ))}
                                {!items.length && <p className="p-8 text-center text-sm text-slate-400">Sin sesiones solicitadas.</p>}
                            </div>
                        </section>

                        <section className="space-y-5">
                            {editing && (
                                <form onSubmit={(event) => { event.preventDefault(); appointmentForm.patch(route('minder.support-appointments.update', editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) }); }} className="space-y-3 rounded-lg border border-slate-200 bg-white p-5">
                                    <h3 className="font-bold text-slate-900">Administrar sesión</h3>
                                    <p className="text-xs text-slate-500">Puedes confirmar la propuesta o cambiarla antes de guardar.</p>
                                    <select value={appointmentForm.data.status} onChange={(event) => appointmentForm.setData('status', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                                        <option value="pending">Pendiente de confirmación</option>
                                        <option value="confirmed">Confirmada</option>
                                        <option value="completed">Completada</option>
                                        <option value="cancelled">Cancelada</option>
                                    </select>
                                    <label className="block">
                                        <span className="mb-1 block text-xs font-semibold text-slate-600">Fecha y hora</span>
                                        <input type="datetime-local" required value={appointmentForm.data.scheduled_at} onChange={(event) => appointmentForm.setData('scheduled_at', event.target.value)} className={`w-full rounded-lg text-sm ${appointmentForm.errors.scheduled_at ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'}`} />
                                        <InputError message={appointmentForm.errors.scheduled_at} className="mt-2" />
                                    </label>
                                    <div>
                                        <input value={appointmentForm.data.meeting_url} onChange={(event) => appointmentForm.setData('meeting_url', event.target.value)} placeholder="Enlace de Google Meet" className={`w-full rounded-lg text-sm ${appointmentForm.errors.meeting_url ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'}`} />
                                        <InputError message={appointmentForm.errors.meeting_url} className="mt-2" />
                                    </div>
                                    <div>
                                        <textarea value={appointmentForm.data.admin_notes} onChange={(event) => appointmentForm.setData('admin_notes', event.target.value)} rows={3} placeholder="Notas internas" className={`w-full rounded-lg text-sm ${appointmentForm.errors.admin_notes ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'}`} />
                                        <InputError message={appointmentForm.errors.admin_notes} className="mt-2" />
                                    </div>
                                    <div className="flex justify-end gap-2"><SecondaryButton type="button" onClick={() => setEditing(null)}>Cancelar</SecondaryButton><PrimaryButton disabled={appointmentForm.processing}>Guardar</PrimaryButton></div>
                                </form>
                            )}

                            <form onSubmit={(event) => { event.preventDefault(); settingsForm.put(route('minder.support-appointments.settings'), { preserveScroll: true }); }} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                                <div><h3 className="font-bold text-slate-900">Disponibilidad</h3><p className="text-sm text-slate-500">Horarios en zona centro de México.</p></div>
                                <input type="email" value={settingsForm.data.support_email} onChange={(event) => settingsForm.setData('support_email', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                                <div className="grid grid-cols-3 gap-2">
                                    <NumberField label="Duración" value={settingsForm.data.duration_minutes} onChange={(value) => settingsForm.setData('duration_minutes', value)} />
                                    <NumberField label="Anticipación" value={settingsForm.data.minimum_notice_hours} onChange={(value) => settingsForm.setData('minimum_notice_hours', value)} />
                                    <NumberField label="Ventana días" value={settingsForm.data.booking_window_days} onChange={(value) => settingsForm.setData('booking_window_days', value)} />
                                </div>
                                <div className="space-y-2">
                                    {days.map(([key, label]) => {
                                        const range = settingsForm.data.weekly_availability[key]?.[0];
                                        return <div key={key} className="grid grid-cols-[90px_1fr_1fr] items-center gap-2">
                                            <label className="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" checked={Boolean(range)} onChange={(event) => toggleDay(key, event.target.checked)} />{label}</label>
                                            <input type="time" disabled={!range} value={range?.start ?? '10:00'} onChange={(event) => settingsForm.setData('weekly_availability', { ...settingsForm.data.weekly_availability, [key]: [{ ...range, start: event.target.value }] })} className="rounded-lg border-slate-200 text-xs" />
                                            <input type="time" disabled={!range} value={range?.end ?? '17:00'} onChange={(event) => settingsForm.setData('weekly_availability', { ...settingsForm.data.weekly_availability, [key]: [{ ...range, end: event.target.value }] })} className="rounded-lg border-slate-200 text-xs" />
                                        </div>;
                                    })}
                                </div>
                                <PrimaryButton disabled={settingsForm.processing}>Guardar disponibilidad</PrimaryButton>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function NumberField({ label, value, onChange }) {
    return <label><span className="mb-1 block text-[10px] font-semibold text-slate-500">{label}</span><input type="number" min="1" value={value} onChange={(event) => onChange(Number(event.target.value))} className="w-full rounded-lg border-slate-200 text-xs" /></label>;
}
