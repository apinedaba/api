import { useState, useEffect } from 'react';
import axios from 'axios';

export default function TemporalitiesEditor() {
    const [temporalities, setTemporalities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        slug: '',
        start_date: '',
        end_date: '',
        notes: '',
    });
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    const sectionKey = 'home';

    // Cargar temporalidades
    const fetchTemporalities = async () => {
        setLoading(true);
        try {
            const response = await axios.get(`/content-temporalities/${sectionKey}`);
            setTemporalities(response.data.temporalities || []);
            setError(null);
        } catch (err) {
            setError('Error al cargar temporalidades');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTemporalities();
    }, []);

    // Abrir modal para crear
    const handleCreateNew = () => {
        setEditing(null);
        setFormData({
            name: '',
            slug: '',
            start_date: '',
            end_date: '',
            notes: '',
        });
        setShowModal(true);
    };

    // Abrir modal para editar propiedades
    const handleEdit = (temporality) => {
        setEditing(temporality);
        setFormData({
            name: temporality.name,
            slug: temporality.slug,
            start_date: temporality.start_date ? temporality.start_date.slice(0, 16) : '',
            end_date: temporality.end_date ? temporality.end_date.slice(0, 16) : '',
            notes: temporality.notes || '',
        });
        setShowModal(true);
    };

    // Guardar temporalidad
    const handleSave = async () => {
        setSaving(true);
        try {
            // Convertir formato: 2026-05-27T10:30 → 2026-05-27 10:30:00
            const formatDate = (dateString) => {
                if (!dateString) return null;
                // Reemplazar T por espacio
                let formatted = dateString.replace('T', ' ');
                // Si no tiene segundos (longitud 16), agregar :00
                if (formatted.length === 16) {
                    formatted += ':00';
                }
                return formatted;
            };

            if (editing) {
                // Actualizar propiedades
                await axios.patch(`/content-temporalities/${editing.id}`, {
                    name: formData.name,
                    start_date: formatDate(formData.start_date),
                    end_date: formatDate(formData.end_date),
                    notes: formData.notes,
                });
                setSuccess('Temporalidad actualizada');
            } else {
                // Crear nueva
                await axios.post('/content-temporalities', {
                    section_key: sectionKey,
                    name: formData.name,
                    slug: formData.slug,
                    start_date: formatDate(formData.start_date),
                    end_date: formatDate(formData.end_date),
                    notes: formData.notes,
                });
                setSuccess('Temporalidad creada');
            }
            setShowModal(false);
            fetchTemporalities();
        } catch (err) {
            setError(err.response?.data?.error || 'Error al guardar');
            console.error(err);
        } finally {
            setSaving(false);
        }
    };

    // Activar temporalidad
    const handleActivate = async (temporalityId) => {
        try {
            await axios.post(`/content-temporalities/${temporalityId}/activate`);
            setSuccess('Temporalidad activada');
            fetchTemporalities();
        } catch (err) {
            setError('Error al activar');
        }
    };

    // Desactivar temporalidad
    const handleDeactivate = async (temporalityId) => {
        try {
            await axios.post(`/content-temporalities/${temporalityId}/deactivate`);
            setSuccess('Temporalidad desactivada');
            fetchTemporalities();
        } catch (err) {
            setError('Error al desactivar');
        }
    };

    // Eliminar temporalidad
    const handleDelete = async (temporalityId) => {
        if (!confirm('¿Eliminar esta temporalidad?')) return;
        try {
            await axios.delete(`/content-temporalities/${temporalityId}`);
            setSuccess('Temporalidad eliminada');
            fetchTemporalities();
        } catch (err) {
            setError('Error al eliminar');
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleString('es-MX');
    };

    const getStatus = (temp) => {
        if (temp.is_active) {
            return { text: 'Activa', color: 'bg-green-100 text-green-800', icon: '✓' };
        }
        if (temp.is_programmed) {
            const now = new Date();
            const start = new Date(temp.start_date);
            const end = new Date(temp.end_date);
            if (now >= start && now <= end) {
                return { text: 'Activa (Programada)', color: 'bg-green-100 text-green-800', icon: '⏰' };
            }
            if (now < start) {
                return { text: 'Programada', color: 'bg-blue-100 text-blue-800', icon: '📅' };
            }
            return { text: 'Vencida', color: 'bg-gray-100 text-gray-800', icon: '⏱️' };
        }
        return { text: 'Inactiva', color: 'bg-gray-100 text-gray-800', icon: '—' };
    };

    return (
        <div className="space-y-6">
            {/* Alertas */}
            {error && (
                <div className="rounded-lg bg-red-50 p-4 border border-red-200">
                    <p className="text-sm text-red-800">❌ {error}</p>
                </div>
            )}
            {success && (
                <div className="rounded-lg bg-green-50 p-4 border border-green-200">
                    <p className="text-sm text-green-800">✓ {success}</p>
                </div>
            )}

            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold text-slate-900">Temporalidades</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Crea versiones del contenido para eventos especiales (Hotsale, Black Friday, etc.)
                    </p>
                </div>
                <button
                    onClick={handleCreateNew}
                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                >
                    ➕ Nueva Temporalidad
                </button>
            </div>

            {/* Lista de temporalidades */}
            {loading ? (
                <div className="text-center py-8">
                    <p className="text-slate-500">Cargando temporalidades...</p>
                </div>
            ) : temporalities.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 p-8 text-center">
                    <p className="text-slate-500">No hay temporalidades aún. Crea la primera para comenzar.</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <table className="w-full">
                        <thead className="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-slate-900 uppercase tracking-wider">Nombre</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-slate-900 uppercase tracking-wider">Estado</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-slate-900 uppercase tracking-wider">Inicio</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-slate-900 uppercase tracking-wider">Fin</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-slate-900 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {temporalities.map((temp) => {
                                const status = getStatus(temp);
                                return (
                                    <tr key={temp.id} className="hover:bg-slate-50">
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col">
                                                <p className="font-medium text-slate-900">{temp.name}</p>
                                                <p className="text-xs text-slate-500">{temp.slug}</p>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ${status.color}`}>
                                                {status.icon} {status.text}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-600">
                                            {formatDate(temp.start_date)}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-600">
                                            {formatDate(temp.end_date)}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                {/* Botón editar contenido */}
                                                <a
                                                    href={`/home-content/temporalities/home/${temp.id}/edit`}
                                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium transition-colors"
                                                >
                                                    {'✏️'} Editar
                                                </a>

                                                {/* Botón activar/desactivar */}
                                                {temp.is_active ? (
                                                    <button
                                                        onClick={() => handleDeactivate(temp.id)}
                                                        className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-yellow-100 hover:bg-yellow-200 text-yellow-700 font-medium transition-colors"
                                                    >
                                                        {'🔔'} Desactivar
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => handleActivate(temp.id)}
                                                        className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-green-100 hover:bg-green-200 text-green-700 font-medium transition-colors"
                                                    >
                                                        {'✓'} Activar
                                                    </button>
                                                )}

                                                {/* Botón editar propiedades */}
                                                <button
                                                    onClick={() => handleEdit(temp)}
                                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium transition-colors"
                                                >
                                                    {'⚙️'}
                                                </button>

                                                {/* Botón eliminar */}
                                                <button
                                                    onClick={() => handleDelete(temp.id)}
                                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 font-medium transition-colors"
                                                >
                                                    {'🗑️'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Modal para crear/editar */}
            {showModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                        <h3 className="text-lg font-semibold text-slate-900">
                            {editing ? 'Editar Temporalidad' : 'Nueva Temporalidad'}
                        </h3>

                        <div className="space-y-4">
                            {/* Nombre */}
                            <div>
                                <label className="block text-sm font-medium text-slate-900 mb-1">
                                    Nombre
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="ej: Hotsale Mayo"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Slug - solo si es nuevo */}
                            {!editing && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-900 mb-1">
                                        Slug (identificador único)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.slug}
                                        onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                                        placeholder="ej: hotsale-mayo"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p className="text-xs text-slate-500 mt-1">Solo letras, números y guiones</p>
                                </div>
                            )}

                            {/* Fecha inicio */}
                            <div>
                                <label className="block text-sm font-medium text-slate-900 mb-1">
                                    Fecha de inicio (opcional)
                                </label>
                                <input
                                    type="datetime-local"
                                    value={formData.start_date}
                                    onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Fecha fin */}
                            <div>
                                <label className="block text-sm font-medium text-slate-900 mb-1">
                                    Fecha de fin (opcional)
                                </label>
                                <input
                                    type="datetime-local"
                                    value={formData.end_date}
                                    onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Notas */}
                            <div>
                                <label className="block text-sm font-medium text-slate-900 mb-1">
                                    Notas (opcional)
                                </label>
                                <textarea
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    placeholder="Detalles sobre esta temporalidad..."
                                    rows="3"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        {/* Botones */}
                        <div className="flex gap-3 pt-4">
                            <button
                                onClick={() => setShowModal(false)}
                                disabled={saving}
                                className="flex-1 px-4 py-2 border border-slate-300 rounded-lg text-slate-900 font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleSave}
                                disabled={saving || !formData.name || (!editing && !formData.slug)}
                                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {saving ? 'Guardando...' : 'Guardar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
