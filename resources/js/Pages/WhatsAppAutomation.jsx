import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import Modal from '@/Components/Modal';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import DataTable from 'react-data-table-component';

const emptyTemplate = {
    key: '',
    template_name: '',
    language: 'es_MX',
    category: 'appointments',
    description: '',
    body_parameters: [],
    buttons: [],
    is_active: true,
};

const channelOptions = [
    { value: 'database', label: 'Base de datos' },
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
];

export default function WhatsAppAutomation({ auth, templates, rules, metrics, fallbacks }) {
    const [editingTemplate, setEditingTemplate] = useState(null);
    const [editingRule, setEditingRule] = useState(null);

    const templateColumns = [
        {
            name: 'Evento',
            selector: row => row.key,
            sortable: true,
            cell: row => (
                <button type="button" onClick={() => setEditingTemplate(row)} className="text-left">
                    <span className="block font-bold text-slate-900">{row.key}</span>
                    <span className="text-xs text-slate-500">{row.description || 'Sin descripcion'}</span>
                </button>
            ),
        },
        {
            name: 'Template Meta',
            selector: row => row.template_name,
            sortable: true,
            cell: row => <span className="font-mono text-xs text-slate-700">{row.template_name}</span>,
        },
        { name: 'Idioma', selector: row => row.language || 'es_MX', width: '110px' },
        {
            name: 'Estado',
            width: '120px',
            cell: row => <Status active={row.is_active} />,
        },
        {
            name: 'Acciones',
            width: '170px',
            cell: row => (
                <div className="flex gap-3">
                    <button type="button" onClick={() => setEditingTemplate(row)} className="text-blue-700 hover:underline">Editar</button>
                    <button type="button" onClick={() => deleteTemplate(row)} className="text-red-600 hover:underline">Eliminar</button>
                </div>
            ),
        },
    ];

    const ruleColumns = [
        {
            name: 'Automatizacion',
            selector: row => row.event_key,
            sortable: true,
            cell: row => (
                <button type="button" onClick={() => setEditingRule(row)} className="text-left">
                    <span className="block font-bold text-slate-900">{row.label}</span>
                    <span className="text-xs text-slate-500">{row.event_key}</span>
                </button>
            ),
        },
        {
            name: 'Canales',
            cell: row => (
                <div className="flex flex-wrap gap-1">
                    {(row.channels || []).map(channel => (
                        <span key={channel} className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">{channel}</span>
                    ))}
                </div>
            ),
        },
        {
            name: 'Template',
            selector: row => row.whatsapp_template_key || '',
            cell: row => <span className="font-mono text-xs">{row.whatsapp_template_key || 'Sin WhatsApp'}</span>,
        },
        {
            name: 'Estado',
            width: '120px',
            cell: row => <Status active={row.is_active} />,
        },
        {
            name: 'Acciones',
            width: '110px',
            cell: row => <button type="button" onClick={() => setEditingRule(row)} className="text-blue-700 hover:underline">Configurar</button>,
        },
    ];

    const deleteTemplate = (template) => {
        if (!window.confirm(`Eliminar ${template.key}?`)) return;
        router.delete(route('whatsapp-automation.templates.destroy', template.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Automatizaciones WhatsApp</h2>}
        >
            <Head title="Automatizaciones WhatsApp" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-emerald-700">Messaging ops</p>
                                <h1 className="text-2xl font-black text-slate-950">Reglas transaccionales</h1>
                                <p className="mt-1 max-w-3xl text-sm text-slate-600">
                                    Configura que pasa cuando se crea, confirma o cancela una sesion. Los envios reales usan estas reglas antes de caer al fallback de entorno.
                                </p>
                            </div>
                            <PrimaryButton onClick={() => setEditingTemplate({ ...emptyTemplate })}>Nuevo template</PrimaryButton>
                        </div>
                    </section>

                    <section className="grid gap-4 md:grid-cols-4">
                        <Metric label="Total enviados" value={metrics.total} />
                        <Metric label="Este mes" value={metrics.month} />
                        <Metric label="Exitosos" value={metrics.sent_total} />
                        <Metric label="Fallidos" value={metrics.failed_total} tone="danger" />
                    </section>

                    <section className="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
                        <div className="rounded-2xl bg-white p-4 shadow-sm">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-lg font-bold text-slate-950">Acciones automatizadas</h2>
                                <span className="text-xs text-slate-500">{rules.length} reglas</span>
                            </div>
                            <DataTable columns={ruleColumns} data={rules} pagination persistTableHead noDataComponent="No hay reglas configuradas." />
                        </div>

                        <div className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="text-lg font-bold text-slate-950">Uso por template este mes</h2>
                            <div className="mt-4 space-y-3">
                                {(metrics.month_by_template || []).map(item => (
                                    <div key={item.key} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                        <span className="font-mono text-xs text-slate-700">{item.key}</span>
                                        <span className="font-bold text-slate-950">{item.total}</span>
                                    </div>
                                ))}
                                {(metrics.month_by_template || []).length === 0 ? <p className="text-sm text-slate-500">Sin envios este mes.</p> : null}
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-slate-950">Templates Meta</h2>
                                <p className="text-xs text-slate-500">Fallbacks de entorno: {Object.keys(fallbacks || {}).length}</p>
                            </div>
                            <PrimaryButton onClick={() => setEditingTemplate({ ...emptyTemplate })}>Nuevo</PrimaryButton>
                        </div>
                        <DataTable columns={templateColumns} data={templates} pagination persistTableHead noDataComponent="No hay templates configurados." />
                    </section>
                </div>
            </div>

            <Modal show={Boolean(editingTemplate)} onClose={() => setEditingTemplate(null)} maxWidth="2xl">
                {editingTemplate ? (
                    <TemplateForm template={editingTemplate} onClose={() => setEditingTemplate(null)} />
                ) : null}
            </Modal>

            <Modal show={Boolean(editingRule)} onClose={() => setEditingRule(null)} maxWidth="2xl">
                {editingRule ? (
                    <RuleForm rule={editingRule} templates={templates} onClose={() => setEditingRule(null)} />
                ) : null}
            </Modal>
        </AuthenticatedLayout>
    );
}

function TemplateForm({ template, onClose }) {
    const { data, setData, post, put, processing, errors } = useForm({
        ...emptyTemplate,
        ...template,
        body_parameters: template.body_parameters || [],
        buttons: template.buttons || [],
    });

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (template.id) {
            put(route('whatsapp-automation.templates.update', template.id), options);
            return;
        }
        post(route('whatsapp-automation.templates.store'), options);
    };

    return (
        <form onSubmit={submit} className="space-y-4 p-6">
            <FormTitle eyebrow={template.id ? 'Editar template' : 'Nuevo template'} title="Template de WhatsApp" />
            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Key MindMeet" error={errors.key}>
                    <input value={data.key} onChange={event => setData('key', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="appointment_created" />
                </Field>
                <Field label="Nombre Meta" error={errors.template_name}>
                    <input value={data.template_name} onChange={event => setData('template_name', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" placeholder="confirm_session" />
                </Field>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Idioma" error={errors.language}>
                    <input value={data.language || 'es_MX'} onChange={event => setData('language', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
                <Field label="Categoria" error={errors.category}>
                    <input value={data.category || ''} onChange={event => setData('category', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
                </Field>
            </div>
            <Field label="Descripcion" error={errors.description}>
                <textarea value={data.description || ''} onChange={event => setData('description', event.target.value)} rows={3} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={data.is_active} onChange={() => setData('is_active', !data.is_active)} className="rounded border-slate-300" />
                Template activo
            </label>
            <FormActions processing={processing} onClose={onClose} label="Guardar template" />
        </form>
    );
}

function RuleForm({ rule, templates, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        ...rule,
        channels: rule.channels || [],
    });

    const toggleChannel = (channel) => {
        const current = data.channels || [];
        setData('channels', current.includes(channel) ? current.filter(item => item !== channel) : [...current, channel]);
    };

    const submit = (event) => {
        event.preventDefault();
        put(route('whatsapp-automation.rules.update', rule.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4 p-6">
            <FormTitle eyebrow={rule.event_key} title="Regla de automatizacion" />
            <Field label="Nombre" error={errors.label}>
                <input value={data.label} onChange={event => setData('label', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <Field label="Descripcion" error={errors.description}>
                <textarea value={data.description || ''} onChange={event => setData('description', event.target.value)} rows={2} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <Field label="Canales" error={errors.channels}>
                <div className="grid gap-2 md:grid-cols-4">
                    {channelOptions.map(channel => (
                        <label key={channel.value} className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input type="checkbox" checked={(data.channels || []).includes(channel.value)} onChange={() => toggleChannel(channel.value)} className="rounded border-slate-300" />
                            {channel.label}
                        </label>
                    ))}
                </div>
            </Field>
            <Field label="Template WhatsApp" error={errors.whatsapp_template_key}>
                <select value={data.whatsapp_template_key || ''} onChange={event => setData('whatsapp_template_key', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm">
                    <option value="">Sin template</option>
                    {templates.map(template => (
                        <option key={template.key} value={template.key}>{template.key} - {template.template_name}</option>
                    ))}
                </select>
            </Field>
            <Field label="Asunto email" error={errors.email_subject}>
                <input value={data.email_subject || ''} onChange={event => setData('email_subject', event.target.value)} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <Field label="Mensaje SMS" error={errors.sms_body}>
                <textarea value={data.sms_body || ''} onChange={event => setData('sms_body', event.target.value)} rows={2} className="w-full rounded-lg border-slate-200 text-sm" />
            </Field>
            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={data.is_active} onChange={() => setData('is_active', !data.is_active)} className="rounded border-slate-300" />
                Regla activa
            </label>
            <FormActions processing={processing} onClose={onClose} label="Guardar regla" />
        </form>
    );
}

function Metric({ label, value, tone = 'default' }) {
    return (
        <div className={`rounded-2xl bg-white p-5 shadow-sm ${tone === 'danger' ? 'border border-red-100' : ''}`}>
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{label}</p>
            <p className={`mt-2 text-3xl font-black ${tone === 'danger' ? 'text-red-600' : 'text-slate-950'}`}>{value || 0}</p>
        </div>
    );
}

function Status({ active }) {
    return (
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
            {active ? 'Activo' : 'Inactivo'}
        </span>
    );
}

function FormTitle({ eyebrow, title }) {
    return (
        <div>
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">{eyebrow}</p>
            <h2 className="text-xl font-bold text-slate-950">{title}</h2>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block text-sm font-semibold text-slate-700">
            <span className="mb-1 block">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs font-normal text-red-600">{error}</span>}
        </label>
    );
}

function FormActions({ processing, onClose, label }) {
    return (
        <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button type="button" onClick={onClose} className="rounded-lg px-4 py-2 text-sm text-slate-600">Cancelar</button>
            <PrimaryButton disabled={processing}>{processing ? 'Guardando...' : label}</PrimaryButton>
        </div>
    );
}
