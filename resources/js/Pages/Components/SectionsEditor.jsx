import { useState } from 'react';

export default function SectionsEditor({ data, onChange }) {
    const [editingIndex, setEditingIndex] = useState(null);
    const [editForm, setEditForm] = useState(null);

    let sections = [];
    try {
        sections = Array.isArray(data.sections)
            ? data.sections
            : JSON.parse(data.sections || '[]');
    } catch (e) {
        console.error('Error parsing sections JSON:', e);
    }

    const handleEditClick = (index) => {
        setEditingIndex(index);
        setEditForm({ ...sections[index] });
    };

    const handleSaveEdit = () => {
        if (editingIndex !== null && editForm) {
            const newSections = JSON.parse(JSON.stringify(sections));
            newSections[editingIndex] = editForm;
            onChange('sections', JSON.stringify(newSections, null, 2));
            setEditingIndex(null);
            setEditForm(null);
        }
    };

    const handleCancelEdit = () => {
        setEditingIndex(null);
        setEditForm(null);
    };

    const toggleVisibility = (index) => {
        const newSections = JSON.parse(JSON.stringify(sections));
        // Cambiar limit entre 0 (oculto) y su valor original
        if (newSections[index].limit === 0) {
            newSections[index].limit = 6; // Mostrar 6 por defecto
        } else {
            newSections[index].limit = 0; // Ocultar
        }
        onChange('sections', JSON.stringify(newSections, null, 2));
    };

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-3">
                {sections.map((section, idx) => (
                    <div
                        key={idx}
                        className={`flex items-center justify-between p-4 rounded-lg border-2 cursor-pointer transition-all ${section.limit === 0
                            ? 'bg-slate-50 border-slate-200 opacity-60'
                            : 'bg-white border-slate-200 hover:border-blue-400'
                            }`}
                    >
                        <div className="flex items-center gap-3 flex-1" onClick={() => handleEditClick(idx)}>
                            <span className="text-2xl">{section.emoji || '📌'}</span>
                            <div className="flex-1">
                                <p className="font-semibold text-slate-900">{section.title}</p>
                                <p className="text-xs text-slate-600">
                                    {section.type === 'slider' && section.filterType
                                        ? `Filtro: ${section.filterType}`
                                        : `Tipo: ${section.type}`}
                                </p>
                            </div>
                            {section.type === 'slider' && (
                                <div className={`px-3 py-1 rounded-full text-xs font-medium ${section.limit > 0 && (!section.professionals || section.professionals.length === 0)
                                    ? 'bg-red-100 text-red-800'
                                    : 'bg-blue-100 text-blue-800'
                                    }`}>
                                    {section.limit || '0'} profesionales
                                </div>
                            )}
                        </div>

                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                toggleVisibility(idx);
                            }}
                            className={`ml-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${section.limit === 0
                                ? 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                                : 'bg-green-100 text-green-700 hover:bg-green-200'
                                }`}
                        >
                            {section.limit === 0 ? '👁️‍🗨️ Oculto' : '👁️ Visible'}
                        </button>
                    </div>
                ))}
            </div>

            {/* MODAL DE EDICIÓN */}
            {editingIndex !== null && editForm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-lg max-w-md w-full p-6 space-y-4">
                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Emoji
                            </label>
                            <input
                                type="text"
                                maxLength={2}
                                value={editForm.emoji || ''}
                                onChange={(e) => setEditForm({ ...editForm, emoji: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-center text-2xl"
                                placeholder="🎯"
                            />
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
                                placeholder="Título del bloque"
                            />
                        </div>

                        {editForm.type === 'slider' && (
                            <div>
                                <label className="block text-sm font-semibold text-slate-900 mb-2">
                                    Cantidad de profesionales
                                </label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="range"
                                        min="0"
                                        max="12"
                                        value={editForm.limit || 6}
                                        onChange={(e) => setEditForm({ ...editForm, limit: parseInt(e.target.value) })}
                                        className="flex-1"
                                    />
                                    <span className="text-lg font-bold text-blue-600 w-12 text-center">
                                        {editForm.limit || 0}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 mt-2">
                                    {editForm.limit === 0 ? '(Oculto)' : `Mostrará ${editForm.limit} profesionales`}
                                </p>
                            </div>
                        )}

                        {editForm.type === 'slider' && editForm.filterType && (
                            <div>
                                <label className="block text-sm font-semibold text-slate-900 mb-2">
                                    Filtro: {editForm.filterType}
                                </label>
                                <div className="bg-slate-50 p-2 rounded-lg text-xs text-slate-600">
                                    {JSON.stringify(editForm.filterValue)}
                                </div>
                                <p className="text-xs text-slate-500 mt-2">
                                    (Para cambiar filtros, edita desde Código JSON)
                                </p>
                            </div>
                        )}

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
