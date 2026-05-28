import { useState, useRef } from 'react';

const uploadToCloudinary = async (file, category = 'general') => {
    try {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('category', category);

        const response = await fetch('/home-content/upload-image', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        console.log('Upload response:', data, 'Status:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${data.error || 'Error desconocido'}`);
        }

        if (!data.success) {
            throw new Error(data.error || 'Error al subir imagen');
        }

        return data.url;
    } catch (error) {
        console.error('Upload error:', error);
        throw error;
    }
};

export default function EspecialidadesEditor({ data, onChange }) {
    const [editingIndex, setEditingIndex] = useState(null);
    const [editForm, setEditForm] = useState(null);
    const [editingImage, setEditingImage] = useState(null);
    const [uploading, setUploading] = useState(false);
    const fileInputRef = useRef(null);

    let especialidades = [];
    try {
        especialidades = Array.isArray(data.especialidades)
            ? data.especialidades
            : JSON.parse(data.especialidades || '[]');
    } catch (e) {
        console.error('Error parsing especialidades JSON:', e);
    }

    const handleEditClick = (index) => {
        setEditingIndex(index);
        setEditForm({ ...especialidades[index] });
    };

    const handleImageClick = () => {
        fileInputRef.current?.click();
    };

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file || !editForm) return;

        setUploading(true);
        try {
            const imageUrl = await uploadToCloudinary(file, 'especialidades');
            setEditForm({ ...editForm, image: imageUrl });
        } catch (error) {
            alert('Error al subir imagen: ' + error.message);
        } finally {
            setUploading(false);
        }
    };

    const handleSaveEdit = () => {
        if (editingIndex !== null && editForm) {
            const newEspecialidades = JSON.parse(JSON.stringify(especialidades));
            newEspecialidades[editingIndex] = editForm;
            onChange('especialidades', JSON.stringify(newEspecialidades, null, 2));
            setEditingIndex(null);
            setEditForm(null);
        }
    };

    const handleCancelEdit = () => {
        setEditingIndex(null);
        setEditForm(null);
    };

    const toggleVisibility = (index) => {
        const newEspecialidades = JSON.parse(JSON.stringify(especialidades));
        newEspecialidades[index].visible = !newEspecialidades[index].visible;
        onChange('especialidades', JSON.stringify(newEspecialidades, null, 2));
    };

    return (
        <div className="space-y-4">
            <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="hidden"
            />

            <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
                {especialidades.map((esp, idx) => (
                    <div
                        key={idx}
                        onClick={() => handleEditClick(idx)}
                        className={`rounded-lg overflow-hidden border-2 cursor-pointer transition-all ${
                            esp.visible
                                ? 'border-slate-300 hover:border-blue-400 hover:shadow-md'
                                : 'border-slate-200 opacity-50'
                        }`}
                    >
                        {/* Imagen */}
                        <div className="relative h-32 bg-slate-100 overflow-hidden group">
                            {esp.image ? (
                                <img
                                    src={esp.image}
                                    alt={esp.title}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-gray-400 text-2xl">
                                    📸
                                </div>
                            )}
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
                        </div>

                        {/* Contenido */}
                        <div className="p-2 bg-white">
                            <p className="text-xs font-semibold text-slate-900 line-clamp-2">{esp.title}</p>
                            <div className="flex items-center justify-between mt-1">
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        toggleVisibility(idx);
                                    }}
                                    className={`text-xs px-2 py-1 rounded-full transition-colors ${
                                        esp.visible
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-slate-200 text-slate-600'
                                    }`}
                                >
                                    {esp.visible ? '👁️' : '👁️‍🗨️'}
                                </button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* MODAL DE EDICIÓN */}
            {editingIndex !== null && editForm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-lg max-w-md w-full max-h-96 overflow-y-auto p-6 space-y-4">
                        <h3 className="text-lg font-bold text-slate-900">Editar Especialidad</h3>

                        {/* Preview imagen */}
                        <div
                            onClick={handleImageClick}
                            className="relative h-40 bg-slate-100 rounded-lg overflow-hidden cursor-pointer group"
                        >
                            {editForm.image ? (
                                <img
                                    src={editForm.image}
                                    alt={editForm.title}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-4xl">
                                    📸
                                </div>
                            )}
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <span className="text-3xl">📤</span>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Título
                            </label>
                            <input
                                type="text"
                                value={editForm.title || ''}
                                onChange={(e) => setEditForm({ ...editForm, title: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                placeholder="Ej: Pareja o Familiar"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Slug (ID)
                            </label>
                            <input
                                type="text"
                                value={editForm.slug || ''}
                                onChange={(e) => setEditForm({ ...editForm, slug: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono text-xs"
                                placeholder="ej: couple_family_therapy"
                                readOnly
                            />
                            <p className="text-xs text-slate-500 mt-1">
                                (No se puede cambiar - es el identificador único)
                            </p>
                        </div>

                        <div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={editForm.visible !== false}
                                    onChange={(e) => setEditForm({ ...editForm, visible: e.target.checked })}
                                    className="w-4 h-4"
                                />
                                <span className="text-sm font-medium text-slate-900">
                                    Visible en el home
                                </span>
                            </label>
                        </div>

                        <div className="flex gap-2 pt-4">
                            <button
                                onClick={handleCancelEdit}
                                className="flex-1 px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-900 rounded-lg font-medium transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleSaveEdit}
                                className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
                            >
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
