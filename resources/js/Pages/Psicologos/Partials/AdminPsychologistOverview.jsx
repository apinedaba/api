import PrimaryButton from '@/Components/PrimaryButton';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const money = (value) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0));

const dayLabels = {
    monday: 'Lunes',
    tuesday: 'Martes',
    wednesday: 'Miercoles',
    thursday: 'Jueves',
    friday: 'Viernes',
    saturday: 'Sabado',
    sunday: 'Domingo',
};

const asArray = (value) => {
    if (Array.isArray(value)) return value;
    if (!value) return [];
    return [value];
};

export default function AdminPsychologistOverview({ psicologo, publicVisibility }) {
    const [membershipType, setMembershipType] = useState(
        psicologo?.membership_type || (psicologo?.has_lifetime_access ? 'lifetime' : 'none')
    );
    const [processing, setProcessing] = useState(false);
    const [membershipProcessing, setMembershipProcessing] = useState(false);
    const [endingAction, setEndingAction] = useState(null);
    const [endProcessing, setEndProcessing] = useState(false);
    const [refund, setRefund] = useState(false);
    const [reason, setReason] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const sessions = psicologo?.configurations?.sesiones || [];
    const packages = psicologo?.session_packages || [];
    const coupons = psicologo?.discount_coupons || [];
    const horarios = psicologo?.horarios || {};
    const subscription = psicologo?.subscription;
    const hasActiveAccess = publicVisibility?.has_billable_access;

    const sessionStats = useMemo(() => {
        const prices = sessions.map((session) => Number(session.precio || 0)).filter(Boolean);
        return {
            total: sessions.length,
            minimum: prices.length ? Math.min(...prices) : null,
        };
    }, [sessions]);

    const ensureVisibility = () => {
        setProcessing(true);
        router.patch(
            route('psicologo.ensure-public-visibility', psicologo.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            }
        );
    };

    const updateMembership = () => {
        setMembershipProcessing(true);
        router.patch(
            route('psicologo.membership.update', psicologo.id),
            { membership_type: membershipType },
            {
                preserveScroll: true,
                onFinish: () => setMembershipProcessing(false),
            }
        );
    };

    const closeEndMembership = () => {
        setEndingAction(null);
        setRefund(false);
        setReason('');
        setConfirmation('');
    };

    const submitEndMembership = () => {
        if (confirmation !== 'CANCELAR') return;
        setEndProcessing(true);
        router.post(
            route('psicologo.membership.end', psicologo.id),
            { action: endingAction, refund, reason, confirmation },
            {
                preserveScroll: true,
                onSuccess: closeEndMembership,
                onFinish: () => setEndProcessing(false),
            }
        );
    };

    const hasCancelableSubscription = Boolean(
        subscription?.stripe_id && !['canceled', 'cancelled'].includes(subscription?.stripe_status)
    );

    return (
        <section className="space-y-6">
            <header className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-blue-700">
                        Control administrativo
                    </p>
                    <h2 className="text-xl font-semibold text-gray-900">
                        Contexto completo del psicologo
                    </h2>
                    <p className="mt-1 max-w-3xl text-sm text-gray-600">
                        Revisa las condiciones de catalogo publico, horarios, sesiones, paquetes y suscripcion antes de activar visibilidad.
                    </p>
                </div>
                <span
                    className={`rounded-full px-4 py-2 text-xs font-bold uppercase ${
                        publicVisibility?.visible
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-800'
                    }`}
                >
                    {publicVisibility?.visible ? 'Visible publicamente' : 'No visible aun'}
                </span>
            </header>

            <div className="grid gap-3 md:grid-cols-5">
                {publicVisibility?.checks?.map((check) => (
                    <div
                        key={check.key}
                        className={`rounded-xl border p-4 ${
                            check.ok
                                ? 'border-green-100 bg-green-50'
                                : 'border-red-100 bg-red-50'
                        }`}
                    >
                        <p className={`text-xs font-bold uppercase ${check.ok ? 'text-green-700' : 'text-red-700'}`}>
                            {check.ok ? 'OK' : 'Pendiente'}
                        </p>
                        <h3 className="mt-1 text-sm font-semibold text-gray-900">{check.label}</h3>
                        <p className="mt-1 text-xs text-gray-600">{check.detail}</p>
                    </div>
                ))}
            </div>

            <div className="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Preparar visibilidad publica</h3>
                        <p className="text-sm text-gray-600">
                            Esta accion marca la cuenta activa, perfil completo, identidad aprobada y correo verificado.
                            La membresia se administra por separado y esta accion no la modifica.
                        </p>
                    </div>
                    <PrimaryButton onClick={ensureVisibility} disabled={processing || !hasActiveAccess}>
                        {processing ? 'Actualizando...' : 'Dejar visible'}
                    </PrimaryButton>
                </div>
            </div>

            <div className="rounded-xl border border-violet-100 bg-violet-50 p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="flex-1">
                        <h3 className="text-base font-semibold text-gray-900">Membresia especial</h3>
                        <p className="mt-1 text-sm text-gray-600">
                            Asignar o retirar una membresia solo modifica el acceso. No completa el perfil, no aprueba identidad y no verifica el correo.
                        </p>
                        <label className="mt-4 block max-w-md text-sm font-medium text-gray-700">
                            Tipo de membresia
                            <select
                                value={membershipType}
                                onChange={(event) => setMembershipType(event.target.value)}
                                className="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
                            >
                                <option value="none">Sin membresia especial</option>
                                <option value="lifetime">Acceso permanente</option>
                                <option value="content_creator">Creador de contenido</option>
                            </select>
                        </label>
                    </div>
                    <PrimaryButton onClick={updateMembership} disabled={membershipProcessing}>
                        {membershipProcessing ? 'Guardando...' : 'Guardar membresia'}
                    </PrimaryButton>
                </div>
            </div>

            <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 className="text-base font-semibold text-red-950">Finalizar membresía</h3>
                        <p className="mt-1 max-w-3xl text-sm text-red-800">
                            Estas acciones son manuales, retiran el acceso, dejan registro administrativo y envían un solo correo al psicólogo.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {psicologo?.has_lifetime_access && (
                            <button type="button" onClick={() => setEndingAction('revoke_lifetime')} className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-100">
                                Retirar membresía permanente
                            </button>
                        )}
                        {hasCancelableSubscription && (
                            <button type="button" onClick={() => setEndingAction('cancel_subscription')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800">
                                Cancelar suscripción
                            </button>
                        )}
                        {!psicologo?.has_lifetime_access && !hasCancelableSubscription && <span className="text-sm font-semibold text-red-700">No hay una membresía vigente para finalizar.</span>}
                    </div>
                </div>
            </div>

            {endingAction && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" onMouseDown={(event) => event.target === event.currentTarget && closeEndMembership()}>
                    <div role="dialog" aria-modal="true" className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                        <h3 className="text-xl font-black text-slate-950">
                            {endingAction === 'revoke_lifetime' ? 'Retirar membresía permanente' : 'Cancelar suscripción de Stripe'}
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Se retirará el acceso de {psicologo?.contacto?.publicName || psicologo?.name} y se enviará el correo de notificación. Esta operación quedará registrada.
                        </p>

                        {endingAction === 'cancel_subscription' && (
                            <label className="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <input type="checkbox" checked={refund} onChange={(event) => setRefund(event.target.checked)} className="mt-0.5 rounded border-amber-400" />
                                <span><strong>Reembolsar el último pago elegible.</strong><br />Stripe procesará una devolución completa al método de pago original.</span>
                            </label>
                        )}

                        <label className="mt-5 block text-sm font-semibold text-slate-700">
                            Motivo interno (opcional)
                            <textarea value={reason} onChange={(event) => setReason(event.target.value)} maxLength={1000} className="mt-1 min-h-24 w-full rounded-lg border-slate-300 text-sm" placeholder="Ej. Cuenta inactiva durante más de 12 meses" />
                        </label>

                        <label className="mt-4 block text-sm font-semibold text-slate-700">
                            Escribe CANCELAR para confirmar
                            <input value={confirmation} onChange={(event) => setConfirmation(event.target.value.toUpperCase())} className="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                        </label>

                        <div className="mt-6 flex justify-end gap-2">
                            <button type="button" onClick={closeEndMembership} disabled={endProcessing} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Volver</button>
                            <button type="button" onClick={submitEndMembership} disabled={endProcessing || confirmation !== 'CANCELAR'} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">
                                {endProcessing ? 'Procesando...' : endingAction === 'revoke_lifetime' ? 'Retirar acceso' : refund ? 'Cancelar y reembolsar' : 'Cancelar suscripción'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-4">
                <InfoCard title="Suscripcion">
                    <p><strong>Estado:</strong> {subscription?.stripe_status || 'Sin suscripcion'}</p>
                    <p><strong>Stripe ID:</strong> {subscription?.stripe_id || 'No disponible'}</p>
                    <p><strong>Plan:</strong> {subscription?.stripe_plan || 'No disponible'}</p>
                    <p><strong>Acceso permanente:</strong> {psicologo?.has_lifetime_access ? 'Si' : 'No'}</p>
                    <p><strong>Membresia especial:</strong> {psicologo?.membership_type === 'content_creator' ? 'Creador de contenido' : psicologo?.has_lifetime_access ? 'Acceso permanente' : 'No asignada'}</p>
                    <p><strong>Fin de prueba:</strong> {subscription?.trial_ends_at || 'No aplica'}</p>
                    <p><strong>Termina en:</strong> {subscription?.ends_at || 'No aplica'}</p>
                </InfoCard>

                <InfoCard title="Sesiones">
                    <p><strong>Total configuradas:</strong> {sessionStats.total}</p>
                    <p><strong>Precio minimo:</strong> {sessionStats.minimum ? money(sessionStats.minimum) : 'Sin precio'}</p>
                    <p><strong>Formatos:</strong> {[...new Set(sessions.map((session) => session.formato).filter(Boolean))].join(', ') || 'Sin formatos'}</p>
                </InfoCard>

                <InfoCard title="Paquetes">
                    <p><strong>Total creados:</strong> {packages.length}</p>
                    <p><strong>Activos:</strong> {packages.filter((item) => item.is_active).length}</p>
                    <p><strong>Destacados:</strong> {packages.filter((item) => item.is_featured).length}</p>
                </InfoCard>

                <InfoCard title="Cupones">
                    <p><strong>Total creados:</strong> {coupons.length}</p>
                    <p><strong>Activos:</strong> {coupons.filter((item) => item.is_active).length}</p>
                    <p><strong>Disponibles hoy:</strong> {coupons.filter((item) => item.is_currently_available).length}</p>
                </InfoCard>
            </div>

            {(psicologo?.membership_administrative_actions || []).length > 0 && (
                <ReadOnlyTable title="Historial administrativo de membresías">
                    {psicologo.membership_administrative_actions.slice(0, 10).map((action) => (
                        <div key={action.id} className="grid gap-2 border-b border-gray-100 py-3 text-sm md:grid-cols-5">
                            <p><strong>Fecha:</strong> {new Date(action.created_at).toLocaleString('es-MX')}</p>
                            <p><strong>Acción:</strong> {action.action === 'revoke_lifetime' ? 'Retiro permanente' : 'Cancelación Stripe'}</p>
                            <p><strong>Estado anterior:</strong> {action.previous_status || 'N/A'}</p>
                            <p><strong>Reembolso:</strong> {action.stripe_refund_id ? `${money(Number(action.refund_amount || 0) / 100)} · ${action.stripe_refund_id}` : 'No'}</p>
                            <p><strong>Correo:</strong> {action.notification_sent_at ? 'Enviado' : action.notification_error ? 'Falló' : 'Pendiente'}</p>
                        </div>
                    ))}
                </ReadOnlyTable>
            )}

            <ReadOnlyTable title="Horarios">
                {Object.entries(dayLabels).map(([key, label]) => {
                    const blocks = asArray(horarios?.[key]);
                    return (
                        <div key={key} className="grid gap-2 border-b border-gray-100 py-3 md:grid-cols-[140px_1fr]">
                            <p className="font-semibold text-gray-800">{label}</p>
                            <div className="flex flex-wrap gap-2">
                                {blocks.length ? blocks.map((block, index) => (
                                    <span key={`${key}-${index}`} className="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">
                                        {typeof block === 'string'
                                            ? block
                                            : `${block.start || block.from || 'Inicio'} - ${block.end || block.to || 'Fin'}`}
                                    </span>
                                )) : (
                                    <span className="text-sm text-gray-500">Sin horario</span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </ReadOnlyTable>

            <ReadOnlyTable title="Configuracion de sesiones">
                {sessions.length ? sessions.map((session, index) => (
                    <div key={`session-${index}`} className="grid gap-2 border-b border-gray-100 py-3 md:grid-cols-5">
                        <p><strong>Tipo:</strong> {session.tipoSesion || 'Sin tipo'}</p>
                        <p><strong>Formato:</strong> {session.formato || 'Sin formato'}</p>
                        <p><strong>Duracion:</strong> {session.duracion || 'N/A'}h</p>
                        <p><strong>Precio:</strong> {money(session.precio)}</p>
                        <p><strong>Categorias:</strong> {asArray(session.categoria).join(', ') || 'Sin categorias'}</p>
                    </div>
                )) : (
                    <p className="text-sm text-gray-500">No tiene sesiones configuradas.</p>
                )}
            </ReadOnlyTable>

            <ReadOnlyTable title="Paquetes de sesiones">
                {packages.length ? packages.map((sessionPackage) => (
                    <div key={sessionPackage.id} className="grid gap-2 border-b border-gray-100 py-3 md:grid-cols-6">
                        <p><strong>Nombre:</strong> {sessionPackage.name}</p>
                        <p><strong>Sesiones:</strong> {sessionPackage.session_count}</p>
                        <p><strong>Total:</strong> {money(sessionPackage.package_total_price)}</p>
                        <p><strong>Por sesion:</strong> {money(sessionPackage.package_session_price)}</p>
                        <p><strong>Promo:</strong> {sessionPackage.has_active_promotion ? money(sessionPackage.promotional_total_price) : 'Sin promo activa'}</p>
                        <p><strong>Estado:</strong> {sessionPackage.is_active ? 'Activo' : 'Oculto'}</p>
                    </div>
                )) : (
                    <p className="text-sm text-gray-500">No tiene paquetes configurados.</p>
                )}
            </ReadOnlyTable>

            <ReadOnlyTable title="Cupones de descuento">
                {coupons.length ? coupons.map((coupon) => (
                    <div key={coupon.id} className="grid gap-2 border-b border-gray-100 py-3 md:grid-cols-6">
                        <p><strong>Codigo:</strong> {coupon.code}</p>
                        <p><strong>Nombre:</strong> {coupon.name}</p>
                        <p><strong>Descuento:</strong> {coupon.discount_type === 'percent' ? `${coupon.discount_value}%` : money(coupon.discount_value)}</p>
                        <p><strong>Aplica:</strong> {coupon.applies_to}</p>
                        <p><strong>Vigencia:</strong> {coupon.starts_at || 'Ahora'} - {coupon.ends_at || 'Sin fin'}</p>
                        <p><strong>Estado:</strong> {coupon.is_currently_available ? 'Disponible' : 'Inactivo'}</p>
                    </div>
                )) : (
                    <p className="text-sm text-gray-500">No tiene cupones configurados.</p>
                )}
            </ReadOnlyTable>
        </section>
    );
}

function InfoCard({ title, children }) {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-gray-700">{title}</h3>
            <div className="space-y-2 text-sm text-gray-600">{children}</div>
        </div>
    );
}

function ReadOnlyTable({ title, children }) {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <h3 className="mb-3 text-base font-semibold text-gray-900">{title}</h3>
            <div>{children}</div>
        </div>
    );
}
