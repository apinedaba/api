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
    const [grantLifetimeAccess, setGrantLifetimeAccess] = useState(false);
    const [processing, setProcessing] = useState(false);

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
            {
                grant_lifetime_access: grantLifetimeAccess,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            }
        );
    };

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
                            Si no hay suscripcion activa/prueba activa, puedes autorizar acceso permanente.
                        </p>
                        {!hasActiveAccess && (
                            <label className="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={grantLifetimeAccess}
                                    onChange={() => setGrantLifetimeAccess((value) => !value)}
                                    className="rounded border-gray-300"
                                />
                                Autorizar acceso permanente si no tiene suscripcion activa
                            </label>
                        )}
                    </div>
                    <PrimaryButton onClick={ensureVisibility} disabled={processing || (!hasActiveAccess && !grantLifetimeAccess)}>
                        {processing ? 'Actualizando...' : 'Dejar visible'}
                    </PrimaryButton>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <InfoCard title="Suscripcion">
                    <p><strong>Estado:</strong> {subscription?.stripe_status || 'Sin suscripcion'}</p>
                    <p><strong>Stripe ID:</strong> {subscription?.stripe_id || 'No disponible'}</p>
                    <p><strong>Plan:</strong> {subscription?.stripe_plan || 'No disponible'}</p>
                    <p><strong>Acceso permanente:</strong> {psicologo?.has_lifetime_access ? 'Si' : 'No'}</p>
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
