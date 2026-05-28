import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import HomeVisualEditor from './Components/HomeVisualEditor';
import axios from 'axios';

export default function TemporalityContentEditor({ auth, temporalityId, sectionKey }) {
    const [temporality, setTemporality] = useState(null);
    const [data, setData] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [view, setView] = useState('visual');
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    // Cargar temporalidad
    useEffect(() => {
        const fetchTemporality = async () => {
            try {
                const response = await axios.get(`/content-temporalities/${sectionKey}/${temporalityId}`);
                setTemporality(response.data.temporality);
                setData(response.data.temporality.data);
                setError(null);
            } catch (err) {
                setError('Error al cargar temporalidad');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };
        fetchTemporality();
    }, [temporalityId, sectionKey]);

    const handleDataChange = (field, value) => {
        setData(prev => ({
            ...prev,
            [field]: typeof value === 'string' ? value : JSON.stringify(value)
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await axios.put(`/content-temporalities/${temporalityId}`, {
                data: data
            });
            setSuccess('Temporalidad actualizada correctamente');
            setError(null);
            setTimeout(() => {
                window.history.back();
            }, 1500);
        } catch (err) {
            setError('Error al guardar temporalidad');
            console.error(err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <div className="py-12">
                    <div className="mx-auto max-w-7xl px-4 text-center">
                        <p className="text-slate-500">Cargando...</p>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    if (!temporality) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <div className="py-12">
                    <div className="mx-auto max-w-7xl px-4 text-center">
                        <p className="text-red-500">Temporalidad no encontrada</p>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-slate-900">Editar: {temporality.name}</h2>}
        >
            <Head title={`Editar: ${temporality.name}`} />

            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 p-6 text-white">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">EDITOR DE TEMPORALIDAD</p>
                                    <h1 className="mt-3 text-3xl font-black tracking-tight">{temporality.name}</h1>
                                    <p className="mt-2 max-w-3xl text-sm text-blue-100">
                                        Edita el contenido específico de esta temporalidad. Los cambios afectarán solo a esta versión.
                                    </p>
                                </div>
                                <button
                                    onClick={() => window.history.back()}
                                    className="px-4 py-2 rounded-lg bg-slate-600 hover:bg-slate-700 text-white font-medium transition-colors"
                                >
                                    ← Volver
                                </button>
                            </div>
                        </div>

                        {/* ALERTAS */}
                        {error && (
                            <div className="p-4 bg-red-50 border-b border-red-200">
                                <p className="text-sm text-red-800">❌ {error}</p>
                            </div>
                        )}
                        {success && (
                            <div className="p-4 bg-green-50 border-b border-green-200">
                                <p className="text-sm text-green-800">✓ {success}</p>
                            </div>
                        )}

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
                        </div>

                        <div className="p-6 space-y-6">
                            {/* VISTA VISUAL */}
                            {view === 'visual' && (
                                <div className="space-y-6">
                                    <HomeVisualEditor data={data} onChange={handleDataChange} />
                                </div>
                            )}

                            {/* VISTA CÓDIGO */}
                            {view === 'code' && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-900 mb-2">
                                        JSON Completo
                                    </label>
                                    <textarea
                                        value={JSON.stringify(data, null, 2)}
                                        onChange={(e) => {
                                            try {
                                                setData(JSON.parse(e.target.value));
                                                setError(null);
                                            } catch (err) {
                                                setError('JSON inválido');
                                            }
                                        }}
                                        className="w-full h-96 p-4 font-mono text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            )}

                            <div className="flex gap-3 justify-end">
                                <button
                                    onClick={() => window.history.back()}
                                    disabled={saving}
                                    className="px-6 py-2 border border-slate-300 rounded-lg text-slate-900 font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={handleSave}
                                    disabled={saving}
                                    className="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    {saving ? '⏳ Guardando...' : '💾 Guardar Cambios'}
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
