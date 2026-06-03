import { useState, useEffect } from 'react';
import { useToast } from '@/Components/Toast';
import Toast from '@/Components/Toast';
import { CheckIcon, XMarkIcon, MagnifyingGlassIcon } from '@heroicons/react/24/solid';

export default function ProfesionalesEditor({ sectionIndex, sections, onChange }) {
    const [allProfessionals, setAllProfessionals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [filterSpecialty, setFilterSpecialty] = useState('');
    const { toasts, addToast, removeToast } = useToast();

    const currentSection = sections[sectionIndex] || {};
    const selectedIds = (currentSection.professionals || []).map(p => p.id);

    // Cargar profesionales de la BD al montar
    useEffect(() => {
        fetchProfessionals();
    }, []);

    const fetchProfessionals = async () => {
        setLoading(true);
        try {
            const response = await fetch('/home-content/professionals');
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Error al obtener profesionales');
            }

            setAllProfessionals(data.professionals);
            addToast(`${data.professionals.length} profesionales disponibles`, 'info', 2000);
        } catch (error) {
            addToast(`Error: ${error.message}`, 'error', 4000);
            console.error('Error fetching professionals:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleToggleProfessional = (professional) => {
        const isSelected = selectedIds.includes(professional.id);
        let updatedProfessionals;

        if (isSelected) {
            // Remover
            updatedProfessionals = (currentSection.professionals || []).filter(
                p => p.id !== professional.id
            );
            addToast(`${professional.name} removido`, 'info', 1500);
        } else {
            // Agregar
            updatedProfessionals = [
                ...(currentSection.professionals || []),
                professional
            ];
            addToast(`${professional.name} agregado`, 'success', 1500);
        }

        // Actualizar el state del padre
        const newSections = [...sections];
        newSections[sectionIndex].professionals = updatedProfessionals;
        onChange('sections', JSON.stringify(newSections, null, 2));
    };

    const handleRemoveAll = () => {
        if (selectedIds.length === 0) {
            addToast('No hay profesionales seleccionados', 'info', 2000);
            return;
        }

        const newSections = [...sections];
        newSections[sectionIndex].professionals = [];
        onChange('sections', JSON.stringify(newSections, null, 2));
        addToast('Todos los profesionales han sido removidos', 'info', 2000);
    };

    // Filtrar profesionales por búsqueda y especialidad
    const filtered = allProfessionals.filter(prof => {
        const matchesSearch = prof.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            prof.specialty.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesSpecialty = !filterSpecialty || prof.specialty.includes(filterSpecialty);
        return matchesSearch && matchesSpecialty;
    });

    // Obtener lista única de especialidades
    const specialties = [...new Set(allProfessionals.flatMap(p =>
        p.specialty.split(', ').filter(s => s !== 'Especialidad no definida')
    ))].sort();

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex justify-between items-start gap-4">
                <div>
                    <h3 className="text-lg font-semibold text-slate-900">Profesionales en este Slider</h3>
                    <p className="text-sm text-slate-500 mt-1">
                        {selectedIds.length} de {allProfessionals.length} seleccionados
                    </p>
                </div>
                <button
                    onClick={fetchProfessionals}
                    disabled={loading}
                    className="px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition-colors disabled:opacity-50"
                >
                    {loading ? '⟳ Cargando...' : '🔄 Actualizar'}
                </button>
            </div>

            {/* Búsqueda y Filtros */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="relative">
                    <MagnifyingGlassIcon className="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Buscar por nombre o especialidad..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <select
                    value={filterSpecialty}
                    onChange={(e) => setFilterSpecialty(e.target.value)}
                    className="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todas las especialidades</option>
                    {specialties.map(specialty => (
                        <option key={specialty} value={specialty}>
                            {specialty}
                        </option>
                    ))}
                </select>
            </div>

            {/* Estado de carga */}
            {loading && (
                <div className="text-center py-12">
                    <div className="animate-spin text-3xl">⟳</div>
                    <p className="text-slate-500 mt-2">Cargando profesionales...</p>
                </div>
            )}

            {/* Grid de profesionales */}
            {!loading && (
                <>
                    {filtered.length === 0 ? (
                        <div className="text-center py-12 bg-slate-50 rounded-lg border border-slate-200">
                            <p className="text-slate-500">
                                {allProfessionals.length === 0
                                    ? 'No hay profesionales disponibles'
                                    : 'No se encontraron profesionales con esos criterios'}
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {filtered.map(professional => {
                                const isSelected = selectedIds.includes(professional.id);
                                return (
                                    <div
                                        key={professional.id}
                                        onClick={() => handleToggleProfessional(professional)}
                                        className={`relative rounded-xl overflow-hidden border-2 cursor-pointer transition-all ${isSelected
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-slate-200 bg-white hover:border-blue-300'
                                            }`}
                                    >
                                        {/* Checkmark en esquina superior derecha */}
                                        {isSelected && (
                                            <div className="absolute top-2 right-2 z-10 bg-blue-500 text-white rounded-full p-1.5 shadow-md">
                                                <CheckIcon className="w-4 h-4" />
                                            </div>
                                        )}

                                        {/* Imagen del profesional */}
                                        <div className="w-full aspect-square overflow-hidden bg-slate-100">
                                            <img
                                                src={professional.image}
                                                alt={professional.name}
                                                className="w-full h-full object-cover"
                                                onError={(e) => {
                                                    e.target.src = '/default-avatar.png';
                                                }}
                                            />
                                        </div>

                                        {/* Información */}
                                        <div className="p-3">
                                            <h4 className="font-semibold text-slate-900 truncate">
                                                {professional.name}
                                            </h4>
                                            <p className="text-xs text-slate-500 mt-0.5 line-clamp-2">
                                                {professional.specialty}
                                            </p>

                                            {/* Rating y Precio */}
                                            <div className="flex items-center justify-between mt-2 pt-2 border-t border-slate-200">
                                                <div className="flex items-center gap-1">
                                                    <span className="text-yellow-500">★</span>
                                                    <span className="text-sm font-medium text-slate-700">
                                                        {professional.rating}
                                                    </span>
                                                    <span className="text-xs text-slate-500">
                                                        ({professional.reviews})
                                                    </span>
                                                </div>
                                                <div className="text-sm font-semibold text-slate-900">
                                                    ${professional.price}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Botones de acción */}
                    {selectedIds.length > 0 && (
                        <div className="flex items-center justify-between pt-4 border-t border-slate-200">
                            <p className="text-sm text-slate-600">
                                <span className="font-semibold">{selectedIds.length}</span> profesionales seleccionados para este slider
                            </p>
                            <button
                                onClick={handleRemoveAll}
                                className="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                            >
                                <XMarkIcon className="w-4 h-4" />
                                Limpiar selección
                            </button>
                        </div>
                    )}
                </>
            )}

            {/* Toast Notifications */}
            {toasts.map(toast => (
                <Toast
                    key={toast.id}
                    message={toast.message}
                    type={toast.type}
                    duration={toast.duration}
                    onClose={() => removeToast(toast.id)}
                />
            ))}
        </div>
    );
}
