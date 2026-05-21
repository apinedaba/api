import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import FotoPerfil from '@/Components/FotoPerfil';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ClinicasIndex({ auth, clinics, owners }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        owner_user_id: '',
        description: '',
        status: 'active',
        base_psychologist_limit: 5,
        addon_psychologist_slots: 0,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('clinics.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Clinicas y agencias</h2>}
        >
            <Head title="Clinicas" />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">Nuevo nivel MindMeet</p>
                            <h1 className="mt-3 text-3xl font-black tracking-tight text-slate-900">Operacion para clinicas y agencias</h1>
                            <p className="mt-2 text-sm text-slate-600">
                                Crea una clinica, asigna psicologos y empieza a operar una agenda mezclada sin romper los espacios
                                individuales de cada profesional.
                            </p>
                        </div>
                    </section>

                    <section className="grid gap-6 lg:grid-cols-[1.1fr,1.9fr]">
                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-slate-900">Crear clinica</h3>
                            <form onSubmit={submit} className="mt-5 space-y-4">
                                <div>
                                    <InputLabel value="Nombre comercial" />
                                    <TextInput
                                        value={data.name}
                                        onChange={(event) => setData('name', event.target.value)}
                                        className="mt-1 block w-full"
                                        placeholder="Clinica MindMeet Centro"
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Responsable / owner" />
                                    <select
                                        value={data.owner_user_id}
                                        onChange={(event) => setData('owner_user_id', event.target.value)}
                                        className="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                    >
                                        <option value="">Sin asignar</option>
                                        {owners.map((owner) => (
                                            <option key={owner.id} value={owner.id}>
                                                {owner.name} · {owner.email}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.owner_user_id} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Descripcion" />
                                    <textarea
                                        rows={4}
                                        value={data.description}
                                        onChange={(event) => setData('description', event.target.value)}
                                        className="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                        placeholder="Describe la unidad, sucursal o agencia."
                                    />
                                    <InputError message={errors.description} className="mt-2" />
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
                                    <InputError message={errors.status} className="mt-2" />
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

                                <PrimaryButton disabled={processing}>{processing ? 'Creando...' : 'Crear clinica'}</PrimaryButton>
                            </form>
                        </div>

                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-slate-900">Espacios creados</h3>
                                    <p className="text-sm text-slate-500">Cada clinica puede reunir varios psicologos y mantener lectura cruzada de su agenda.</p>
                                </div>
                                <div className="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                                    {clinics.length} registradas
                                </div>
                            </div>

                            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                                {clinics.map((clinic) => (
                                    <Link
                                        key={clinic.id}
                                        href={route('clinics.show', clinic.id)}
                                        className="rounded-3xl border border-slate-200 bg-slate-50 p-5 transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-white hover:shadow-md"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">{clinic.slug}</p>
                                                <h4 className="mt-2 text-xl font-bold text-slate-900">{clinic.name}</h4>
                                                <p className="mt-1 text-sm text-slate-500">
                                                    {clinic.owner ? `${clinic.owner.name} · ${clinic.owner.email}` : 'Sin responsable asignado'}
                                                </p>
                                            </div>
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                clinic.status === 'active' ? 'bg-emerald-100 text-emerald-700' :
                                                clinic.status === 'paused' ? 'bg-amber-100 text-amber-700' :
                                                'bg-slate-200 text-slate-700'
                                            }`}>
                                                {clinic.status}
                                            </span>
                                        </div>

                                        <p className="mt-4 line-clamp-2 text-sm text-slate-600">
                                            {clinic.description || 'Sin descripcion todavia. Puedes usar este espacio para una sucursal, equipo o agencia completa.'}
                                        </p>

                                        <div className="mt-5 flex items-center gap-3 text-sm text-slate-600">
                                            <span className="rounded-2xl bg-white px-3 py-2 font-medium shadow-sm">
                                                {clinic.memberships_count} miembros
                                            </span>
                                            <span className="rounded-2xl bg-white px-3 py-2 font-medium shadow-sm">
                                                {clinic.psychologists.length} psicologos visibles
                                            </span>
                                            <span className="rounded-2xl bg-white px-3 py-2 font-medium shadow-sm">
                                                {clinic.capacity_used}/{clinic.capacity_total} espacios
                                            </span>
                                        </div>

                                        <div className="mt-5 flex items-center gap-2">
                                            {clinic.psychologists.slice(0, 4).map((professional) => (
                                                <div key={professional.id} className="flex items-center gap-2 rounded-full bg-white px-2 py-1 shadow-sm">
                                                    <FotoPerfil image={professional.image} name={professional.name} className="h-8 w-8 rounded-full" />
                                                    <span className="max-w-[120px] truncate text-xs font-medium text-slate-700">{professional.name}</span>
                                                </div>
                                            ))}
                                            {clinic.psychologists.length > 4 ? (
                                                <span className="text-xs font-semibold text-slate-500">+{clinic.psychologists.length - 4} mas</span>
                                            ) : null}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
