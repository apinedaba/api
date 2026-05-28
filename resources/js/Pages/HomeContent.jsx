import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import HomeVisualEditor from './Components/HomeVisualEditor';
import TemporalitiesEditor from './Components/TemporalitiesEditor';
import axios from 'axios';

export default function HomeContent({ auth, editor }) {
    const [view, setView] = useState('visual'); // 'visual', 'code' o 'temporalities'
    const [showHistory, setShowHistory] = useState(false);
    const [history, setHistory] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [selectedVersion, setSelectedVersion] = useState(null);
    const [restoring, setRestoring] = useState(false);
    const [lastUpdate, setLastUpdate] = useState(null);

    const { data, setData, put, processing, errors } = useForm({
        hero: editor.hero || '',
        homeSlider: editor.homeSlider || '[]',
        promotions: editor.promotions || '[]',
        especialidades: editor.especialidades || '[]',
        sections: editor.sections || '[]',
        uploadedImages: editor.uploadedImages || '{"recent":[]}',
        extraBlocks: editor.extraBlocks || '{}',
    });

    // Obtener historial de versiones
    const fetchHistory = async () => {
        setLoadingHistory(true);
        try {
            const response = await axios.get(route('home-content.versions.history'));
            const historyData = response.data.history || [];
            setHistory(historyData);
            if (historyData.length > 0) {
                setLastUpdate(historyData[0]);
            }
        } catch (error) {
            console.error('Error fetching history:', error);
        } finally {
            setLoadingHistory(false);
        }
    };

    // Cargar historial al abrir modal
    useEffect(() => {
        if (showHistory && history.length === 0) {
            fetchHistory();
        }
    }, [showHistory]);

    // Cargar historial inicial al montar
    useEffect(() => {
        fetchHistory();
    }, []);

    // Restaurar a versión anterior
    const restoreVersion = async () => {
        if (!selectedVersion) return;

        setRestoring(true);
        try {
            const response = await axios.post(route('home-content.versions.restore'), {
                version: selectedVersion,
            });

            if (response.data.success) {
                // Recargar la página para obtener los datos restaurados
                window.location.reload();
            }
        } catch (error) {
            console.error('Error restoring version:', error);
            alert('Error al restaurar versión');
        } finally {
            setRestoring(false);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        put(route('home-content.update'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Contenido del home</h2>}
        >
            <Head title="Contenido del home" />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 p-6 text-white">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">CMS VISUAL</p>
                                    <h1 className="mt-3 text-3xl font-black tracking-tight">Editor Visual del Home</h1>
                                    <p className="mt-2 max-w-3xl text-sm text-blue-100">
                                        Edita imágenes visualmente: haz clic para cambiarlas. El JSON se actualiza automáticamente. Cambia a vista "Código" para editar JSON completo.
                                    </p>
                                </div>

                                {/* Botones de Historial y Restaurar */}
                                <div className="ml-6 flex flex-col gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowHistory(!showHistory)}
                                        className="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 transition-colors"
                                    >
                                        📋 Historial
                                    </button>
                                </div>
                            </div>

                            {/* Información de último cambio */}
                            {lastUpdate && (
                                <div className="mt-4 pt-4 border-t border-blue-400">
                                    <p className="text-xs text-blue-100">
                                        <strong>Último cambio:</strong> {new Date(lastUpdate.changedAt).toLocaleString('es-MX')}
                                        {lastUpdate.changedBy && ` por ${lastUpdate.changedBy}`}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* TABS */}
                        <div className="border-b border-slate-200 bg-slate-50 px-6 py-4 flex gap-4">
                            <button
                                onClick={() => setView('visual')}
                                className={`px-4 py-2 rounded-lg font-medium transition-colors ${view === 'visual'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                👁️ Vista Visual
                            </button>
                            <button
                                onClick={() => setView('code')}
                                className={`px-4 py-2 rounded-lg font-medium transition-colors ${view === 'code'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                {'<>'} Código JSON
                            </button>
                            <button
                                onClick={() => setView('temporalities')}
                                className={`px-4 py-2 rounded-lg font-medium transition-colors ${view === 'temporalities'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                ⏰ Temporalidades
                            </button>
                        </div>

                        {/* MODAL DE HISTORIAL */}
                        {showHistory && (
                            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
                                <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-lg">
                                    <div className="flex items-center justify-between mb-4">
                                        <h2 className="text-2xl font-bold text-slate-900">📋 Historial de Cambios</h2>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowHistory(false);
                                                setSelectedVersion(null);
                                            }}
                                            className="text-slate-400 hover:text-slate-600"
                                        >
                                            ✕
                                        </button>
                                    </div>

                                    {loadingHistory ? (
                                        <p className="text-slate-600 text-center py-8">Cargando historial...</p>
                                    ) : history.length === 0 ? (
                                        <p className="text-slate-600 text-center py-8">No hay cambios registrados aún</p>
                                    ) : (
                                        <div className="space-y-4">
                                            {/* Lista de versiones */}
                                            <div className="max-h-64 overflow-y-auto space-y-2 border border-slate-200 rounded-lg p-4 bg-slate-50">
                                                {history.map((item, index) => (
                                                    <div
                                                        key={index}
                                                        onClick={() => setSelectedVersion(item.version)}
                                                        className={`p-3 rounded-lg cursor-pointer transition-colors ${selectedVersion === item.version
                                                            ? 'bg-blue-100 border-2 border-blue-500'
                                                            : 'bg-white border border-slate-200 hover:bg-slate-100'
                                                            }`}
                                                    >
                                                        <p className="font-semibold text-slate-900">Versión {item.version}</p>
                                                        <p className="text-xs text-slate-500 mt-1">
                                                            {new Date(item.changedAt).toLocaleString('es-MX')}
                                                        </p>
                                                        <p className="text-xs text-slate-600">
                                                            Por: <strong>{item.changedBy || 'Sistema'}</strong>
                                                        </p>
                                                        {item.reason && (
                                                            <p className="text-xs text-slate-600 mt-1 italic">{item.reason}</p>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>

                                            {/* Botón para restaurar */}
                                            <div className="flex gap-3 pt-4">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowHistory(false);
                                                        setSelectedVersion(null);
                                                    }}
                                                    className="flex-1 px-4 py-2 rounded-lg bg-slate-200 text-slate-900 font-medium hover:bg-slate-300 transition-colors"
                                                >
                                                    Cerrar
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={restoreVersion}
                                                    disabled={!selectedVersion || restoring}
                                                    className={`flex-1 px-4 py-2 rounded-lg font-medium transition-colors ${selectedVersion && !restoring
                                                        ? 'bg-green-600 text-white hover:bg-green-700'
                                                        : 'bg-slate-300 text-slate-500 cursor-not-allowed'
                                                        }`}
                                                >
                                                    {restoring ? 'Restaurando...' : `✓ Restaurar a versión ${selectedVersion || ''}`}
                                                </button>
                                            </div>

                                            <p className="text-xs text-slate-500 text-center">
                                                📌 Selecciona una versión y haz clic en "Restaurar" para volver a ella
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-6 p-6">
                            {errors.json ? (
                                <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                                    {errors.json}
                                </div>
                            ) : null}

                            {/* VISTA VISUAL */}
                            {view === 'visual' && (
                                <div className="space-y-6">
                                    <HomeVisualEditor data={data} onChange={setData} />
                                </div>
                            )}

                            {/* VISTA CÓDIGO */}
                            {view === 'code' && (
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <Field label="Hero principal" hint="URL de la imagen hero del home." error={errors.hero}>
                                        <input
                                            value={data.hero}
                                            onChange={(event) => setData('hero', event.target.value)}
                                            className="w-full rounded-xl border-slate-200 text-sm shadow-sm"
                                            placeholder="https://..."
                                        />
                                    </Field>

                                    <JsonField
                                        label="Slider del home"
                                        hint="Arreglo JSON. Aqui puedes cambiar slides, links, copys e imagenes mobile/desktop."
                                        value={data.homeSlider}
                                        onChange={(value) => setData('homeSlider', value)}
                                        error={errors.homeSlider}
                                    />
                                    <JsonField
                                        label="Promociones"
                                        hint="Arreglo JSON. Ideal para banners, descuentos y campañas temporales."
                                        value={data.promotions}
                                        onChange={(value) => setData('promotions', value)}
                                        error={errors.promotions}
                                    />
                                    <JsonField
                                        label="Especialidades"
                                        hint="Arreglo JSON para especialidades más buscadas. Edita: title, image, slug, visible."
                                        value={data.especialidades}
                                        onChange={(value) => setData('especialidades', value)}
                                        error={errors.especialidades}
                                    />
                                    <JsonField
                                        label="Bloques / secciones del home"
                                        hint="Arreglo JSON. Aqui puedes reordenar, ocultar o crear nuevos bloques del home a placer."
                                        value={data.sections}
                                        onChange={(value) => setData('sections', value)}
                                        error={errors.sections}
                                    />

                                    <JsonField
                                        label="📚 Historial de Imágenes"
                                        hint="Objeto con array 'recent' que almacena todas las imágenes subidas con su historial."
                                        value={data.uploadedImages}
                                        onChange={(value) => setData('uploadedImages', value)}
                                        error={errors.uploadedImages}
                                    />

                                    <JsonField
                                        label="Bloques extra"
                                        hint="Objeto JSON para cualquier otra llave estatica que quieras agregar al home sin tocar codigo."
                                        value={data.extraBlocks}
                                        onChange={(value) => setData('extraBlocks', value)}
                                        error={errors.extraBlocks}
                                        rows={16}
                                    />

                                    <div className="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-slate-700 lg:col-span-2">
                                        <p className="font-semibold text-slate-900">Recomendaciones</p>
                                        <ul className="mt-2 space-y-2">
                                            <li>Usa JSON valido en cada bloque. Si algo no parsea, el sistema no guardara cambios.</li>
                                            <li>Para nuevos bloques del home, agrega objetos en <strong>sections</strong> con su <strong>type</strong> y props.</li>
                                            <li>Si necesitas agregar nuevas llaves top-level fuera del esquema base, usa <strong>Bloques extra</strong>.</li>
                                        </ul>
                                    </div>
                                </div>
                            )}

                            {view === 'visual' && (
                                <div className="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-slate-700">
                                    <p className="font-semibold text-slate-900">💡 Tips del editor visual</p>
                                    <ul className="mt-2 space-y-2">
                                        <li>✅ <strong>Haz clic en cualquier imagen</strong> para cambiarla desde tu ordenador</li>
                                        <li>✅ El JSON se actualiza <strong>automáticamente</strong> con las nuevas URLs</li>
                                        <li>✅ En el slider, navega con los botones para ver todos los slides</li>
                                        <li>✅ Para editar textos o JSON completo, cambia a la pestaña <strong>Código JSON</strong></li>
                                    </ul>
                                </div>
                            )}

                            {/* VISTA TEMPORALIDADES */}
                            {view === 'temporalities' && (
                                <div className="space-y-6">
                                    <TemporalitiesEditor />
                                </div>
                            )}

                            {view !== 'temporalities' && (
                                <div className="flex justify-end">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Guardando...' : 'Guardar contenido'}
                                    </PrimaryButton>
                                </div>
                            )}
                        </form>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-semibold text-slate-700">{label}</span>
            {children}
            {hint ? <span className="mt-1 block text-xs text-slate-500">{hint}</span> : null}
            {error ? <span className="mt-1 block text-xs font-semibold text-red-600">{error}</span> : null}
        </label>
    );
}

function JsonField({ label, hint, value, onChange, error, rows = 14 }) {
    return (
        <Field label={label} hint={hint} error={error}>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={rows}
                className="w-full rounded-2xl border-slate-200 font-mono text-xs shadow-sm"
                spellCheck="false"
            />
        </Field>
    );
}
