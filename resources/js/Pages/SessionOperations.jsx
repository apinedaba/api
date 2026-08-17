import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import toast from 'react-hot-toast';

const initialSession = {
    patient_id: '', user_id: '', fecha_inicio: '', fecha_fin: '', tipo: 'virtual',
    state: 'Creado', motivo: 'Sesion programada', observaciones: '', assign: true,
};

const initialPayment = {
    enabled: false, amount: '', payment_method: 'transfer', status: 'completed', concepto: 'Pago de sesion', receipt_url: '',
};

export default function SessionOperations({ patients, psychologists, recentAppointments }) {
    const { auth } = usePage().props;
    const [session, setSession] = useState(initialSession);
    const [payment, setPayment] = useState(initialPayment);
    const [saving, setSaving] = useState(false);
    const [query, setQuery] = useState('');

    const selectedPatient = useMemo(
        () => patients.find((patient) => String(patient.id) === String(session.patient_id)),
        [patients, session.patient_id],
    );
    const filteredAppointments = useMemo(() => {
        const term = query.trim().toLowerCase();
        if (!term) return recentAppointments;
        return recentAppointments.filter((item) => [item.patient, item.psychologist, item.title, item.state]
            .some((value) => String(value || '').toLowerCase().includes(term)));
    }, [query, recentAppointments]);

    const updateSession = (event) => {
        const { name, value, type, checked } = event.target;
        setSession((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
        if (name === 'fecha_inicio' && value) {
            const end = new Date(new Date(value).getTime() + 50 * 60000);
            const localEnd = new Date(end.getTime() - end.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            setSession((current) => ({ ...current, fecha_inicio: value, fecha_fin: localEnd }));
        }
    };

    const submit = async (event) => {
        event.preventDefault();
        if (!session.patient_id || !session.user_id) return toast.error('Selecciona paciente y psicologo.');
        if (!session.fecha_inicio || !session.fecha_fin) return toast.error('Indica fecha de inicio y fin.');
        if (payment.enabled && (!payment.amount || Number(payment.amount) <= 0)) return toast.error('Captura un importe valido.');

        setSaving(true);
        try {
            if (session.assign) {
                await axios.post(`/admin/api/pacientes/${session.patient_id}/asignar-psicologo`, {
                    psychologist_id: session.user_id,
                    set_as_active: true,
                });
            }
            const appointmentResponse = await axios.post('/admin/api/citas', session);
            const appointmentId = appointmentResponse.data?.data?.id;
            if (payment.enabled && appointmentId) {
                await axios.post(`/admin/api/citas/${appointmentId}/pagos`, payment);
            }
            toast.success(payment.enabled ? 'Sesion, asignacion y pago registrados.' : 'Sesion registrada correctamente.');
            setSession(initialSession);
            setPayment(initialPayment);
            router.reload({ only: ['patients', 'recentAppointments'] });
        } catch (error) {
            toast.error(error.response?.data?.message || 'No fue posible completar la operacion.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl font-semibold text-slate-900">Sesiones y pagos</h2>}>
            <Head title="Sesiones y pagos" />
            <main className="min-w-0 overflow-x-hidden px-4 py-6 sm:px-6 lg:px-8">
                <div className="mx-auto min-w-0 max-w-7xl space-y-6">
                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                        <div className="mb-6">
                            <h1 className="text-xl font-bold text-slate-900">Registrar sesion</h1>
                            <p className="mt-1 text-sm text-slate-500">Asigna el paciente, agenda la sesion y registra el pago en un solo flujo.</p>
                        </div>
                        <form onSubmit={submit} className="min-w-0 space-y-6">
                            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                                <Field label="Paciente">
                                    <select name="patient_id" value={session.patient_id} onChange={updateSession} required className="admin-control">
                                        <option value="">Seleccionar paciente</option>
                                        {patients.map((patient) => <option key={patient.id} value={patient.id}>{patient.name} — {patient.email || patient.phone || 'sin contacto'}</option>)}
                                    </select>
                                </Field>
                                <Field label="Psicologo">
                                    <select name="user_id" value={session.user_id} onChange={updateSession} required className="admin-control">
                                        <option value="">Seleccionar psicologo</option>
                                        {psychologists.map((psychologist) => <option key={psychologist.id} value={psychologist.id}>{psychologist.name} {!psychologist.activo ? '(inactivo)' : ''}</option>)}
                                    </select>
                                </Field>
                                <Field label="Inicio"><input className="admin-control" type="datetime-local" name="fecha_inicio" value={session.fecha_inicio} onChange={updateSession} required /></Field>
                                <Field label="Fin"><input className="admin-control" type="datetime-local" name="fecha_fin" value={session.fecha_fin} onChange={updateSession} required /></Field>
                                <Field label="Formato"><select className="admin-control" name="tipo" value={session.tipo} onChange={updateSession}><option value="virtual">Virtual</option><option value="presencial">Presencial</option></select></Field>
                                <Field label="Estado"><select className="admin-control" name="state" value={session.state} onChange={updateSession}><option value="Creado">Creado</option><option value="Confirmado">Confirmado</option><option value="Completado">Completado</option></select></Field>
                                <Field label="Motivo"><input className="admin-control" name="motivo" value={session.motivo} onChange={updateSession} /></Field>
                                <Field label="Observaciones"><input className="admin-control" name="observaciones" value={session.observaciones} onChange={updateSession} /></Field>
                            </div>
                            <label className="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                                <input className="mt-0.5 rounded border-slate-300" type="checkbox" name="assign" checked={session.assign} onChange={updateSession} />
                                <span>Asignar este psicologo como principal del paciente. {selectedPatient?.connections?.length ? `Actualmente tiene ${selectedPatient.connections.length} relacion(es).` : ''}</span>
                            </label>

                            <div className="rounded-2xl border border-slate-200 p-4">
                                <label className="flex items-center gap-3 font-semibold text-slate-900"><input type="checkbox" checked={payment.enabled} onChange={(e) => setPayment((current) => ({ ...current, enabled: e.target.checked }))} /> Registrar pago ahora</label>
                                {payment.enabled && <div className="mt-4 grid min-w-0 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <Field label="Importe (MXN)"><input className="admin-control" type="number" min="0.01" step="0.01" value={payment.amount} onChange={(e) => setPayment({ ...payment, amount: e.target.value })} /></Field>
                                    <Field label="Metodo"><select className="admin-control" value={payment.payment_method} onChange={(e) => setPayment({ ...payment, payment_method: e.target.value })}><option value="transfer">Transferencia</option><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="deposit">Deposito</option><option value="other">Otro</option></select></Field>
                                    <Field label="Estado"><select className="admin-control" value={payment.status} onChange={(e) => setPayment({ ...payment, status: e.target.value })}><option value="completed">Pagado</option><option value="pending">Pendiente</option></select></Field>
                                    <Field label="Concepto"><input className="admin-control" value={payment.concepto} onChange={(e) => setPayment({ ...payment, concepto: e.target.value })} /></Field>
                                    <Field label="URL de comprobante (opcional)"><input className="admin-control" type="url" value={payment.receipt_url} onChange={(e) => setPayment({ ...payment, receipt_url: e.target.value })} /></Field>
                                </div>}
                            </div>
                            <div className="flex justify-end"><button disabled={saving} className="w-full rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:opacity-50 sm:w-auto">{saving ? 'Guardando...' : 'Crear sesion'}</button></div>
                        </form>
                    </section>

                    <section className="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><h2 className="font-bold text-slate-900">Sesiones recientes</h2><p className="text-sm text-slate-500">Ultimos 30 registros</p></div><input className="admin-control sm:max-w-xs" placeholder="Buscar paciente o psicologo" value={query} onChange={(e) => setQuery(e.target.value)} /></div>
                        <div className="max-w-full overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500"><tr><Th>Fecha</Th><Th>Paciente</Th><Th>Psicologo</Th><Th>Estado</Th><Th>Pago</Th></tr></thead><tbody className="divide-y divide-slate-100">{filteredAppointments.map((item) => <tr key={item.id}><Td>{new Date(item.start).toLocaleString('es-MX')}</Td><Td>{item.patient || 'Sin paciente'}</Td><Td>{item.psychologist || 'Sin psicologo'}</Td><Td>{item.state || 'Pendiente'}</Td><Td>{item.payment_status === 'paid' ? `Pagado $${item.paid_amount.toFixed(2)}` : item.paid_amount > 0 ? `$${item.paid_amount.toFixed(2)}` : 'Sin pago'}</Td></tr>)}</tbody></table>
                        </div>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function Field({ label, children }) { return <label className="block min-w-0"><span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>{children}</label>; }
function Th({ children }) { return <th className="whitespace-nowrap px-4 py-3 font-semibold">{children}</th>; }
function Td({ children }) { return <td className="max-w-xs truncate px-4 py-3 text-slate-700">{children}</td>; }
