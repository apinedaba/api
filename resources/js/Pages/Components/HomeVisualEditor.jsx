import { useState, useRef } from 'react';
import SectionsEditor from './SectionsEditor';
import EspecialidadesEditor from './EspecialidadesEditor';
import { useToast } from '@/Components/Toast';
import Toast from '@/Components/Toast';

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

export default function HomeVisualEditor({ data, onChange }) {
    const [editingImage, setEditingImage] = useState(null);
    const [currentSlideIndex, setCurrentSlideIndex] = useState(0);
    const [showSlideModal, setShowSlideModal] = useState(false);
    const [editingSlideIndex, setEditingSlideIndex] = useState(null);
    const [slideForm, setSlideForm] = useState(null);
    const [uploading, setUploading] = useState(false);
    const fileInputRef = useRef(null);
    const slideImageInputRef = useRef(null);
    const slideImageMobileInputRef = useRef(null);
    const { toasts, addToast, removeToast } = useToast();

    const handleImageClick = (type, index, field = 'imageUrl') => {
        setEditingImage({ type, index, field });
        fileInputRef.current?.click();
    };

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file || !editingImage) return;

        setUploading(true);
        try {
            const imageUrl = await uploadToCloudinary(file, editingImage.type);

            if (editingImage.type === 'homeSlider') {
                const currentSlides = Array.isArray(data.homeSlider)
                    ? data.homeSlider
                    : JSON.parse(data.homeSlider || '[]');
                if (currentSlides[editingImage.index]) {
                    currentSlides[editingImage.index][editingImage.field] = imageUrl;
                    onChange('homeSlider', JSON.stringify(currentSlides, null, 2));
                }
            } else if (editingImage.type === 'promotions') {
                const currentPromotions = Array.isArray(data.promotions)
                    ? data.promotions
                    : JSON.parse(data.promotions || '[]');
                if (currentPromotions[editingImage.index]) {
                    currentPromotions[editingImage.index].url = imageUrl;
                    onChange('promotions', JSON.stringify(currentPromotions, null, 2));
                }
            } else if (editingImage.type === 'hero') {
                onChange('hero', imageUrl);
            }

            setEditingImage(null);
            // Resetear el input para poder subir la misma imagen nuevamente
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            addToast('Imagen subida correctamente', 'success', 2000);
        } catch (error) {
            addToast(`Error: ${error.message}`, 'error', 4000);
        } finally {
            setUploading(false);
        }
    };

    // Funciones para gestionar slides
    const handleAddSlide = () => {
        setEditingSlideIndex(null);
        setSlideForm({ title: '', subtitle: '', link: '', imageUrl: '' });
        setShowSlideModal(true);
    };

    const handleEditSlide = (index) => {
        setEditingSlideIndex(index);
        setSlideForm({ ...slides[index] });
        setShowSlideModal(true);
    };

    const handleDeleteSlide = (index) => {
        const newSlides = slides.filter((_, i) => i !== index);
        onChange('homeSlider', JSON.stringify(newSlides, null, 2));
        if (currentSlideIndex >= newSlides.length) {
            setCurrentSlideIndex(Math.max(0, newSlides.length - 1));
        }
    };

    const handleMoveSlide = (index, direction) => {
        const newSlides = [...slides];
        const newIndex = direction === 'up' ? index - 1 : index + 1;
        [newSlides[index], newSlides[newIndex]] = [newSlides[newIndex], newSlides[index]];
        onChange('homeSlider', JSON.stringify(newSlides, null, 2));
        setCurrentSlideIndex(newIndex);
    };

    const handleSlideImageUpload = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        try {
            const imageUrl = await uploadToCloudinary(file, 'slider');
            setSlideForm({ ...slideForm, imageUrl });
            addToast('Imagen del slide subida', 'success', 2000);
        } catch (error) {
            addToast(`Error: ${error.message}`, 'error', 4000);
        } finally {
            setUploading(false);
        }
    };

    const handleSlideImageUploadMobile = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        try {
            const imageUrl = await uploadToCloudinary(file, 'slider-mobile');
            setSlideForm({ ...slideForm, imageUrlMobile: imageUrl });
            addToast('Imagen móvil del slide subida', 'success', 2000);
        } catch (error) {
            addToast(`Error: ${error.message}`, 'error', 4000);
        } finally {
            setUploading(false);
        }
    };

    const handleSaveSlide = () => {
        if (!slideForm || !slideForm.imageUrl) {
            addToast('La imagen para desktop es requerida', 'warning', 3000);
            return;
        }

        const newSlides = [...slides];
        if (editingSlideIndex !== null) {
            newSlides[editingSlideIndex] = slideForm;
        } else {
            newSlides.push(slideForm);
        }

        onChange('homeSlider', JSON.stringify(newSlides, null, 2));
        setShowSlideModal(false);
        setSlideForm(null);
        setEditingSlideIndex(null);
    };

    const handleCancelSlideModal = () => {
        setShowSlideModal(false);
        setSlideForm(null);
        setEditingSlideIndex(null);
    };

    let slides = [];
    let promotions = [];

    try {
        slides = Array.isArray(data.homeSlider)
            ? data.homeSlider
            : JSON.parse(data.homeSlider || '[]');
        promotions = Array.isArray(data.promotions)
            ? data.promotions
            : JSON.parse(data.promotions || '[]');
    } catch (e) {
        console.error('Error parsing JSON:', e);
    }

    const currentSlide = slides[currentSlideIndex] || {};

    return (
        <div className="space-y-6">
            <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="hidden"
            />

            {/* HERO SECTION */}
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div className="relative bg-slate-100 aspect-video cursor-pointer group"
                    onClick={() => handleImageClick('hero')}>
                    {data.hero ? (
                        <img
                            src={data.hero}
                            alt="Hero"
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center bg-slate-200">
                            <div className="text-center">
                                <div className="mx-auto text-4xl mb-2">📸</div>
                                <p className="text-sm text-slate-500">Imagen hero</p>
                            </div>
                        </div>
                    )}
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <span className="text-3xl">📤</span>
                    </div>
                </div>
            </div>

            {/* SLIDER SECTION */}
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 bg-slate-50 p-4 flex items-center justify-between">
                    <div>
                        <h3 className="font-semibold text-slate-900">Slider del Home</h3>
                        <p className="text-xs text-slate-500 mt-1">Haz clic en las imágenes para cambiarlas. Gestiona los slides con los botones.</p>
                    </div>
                    <button
                        onClick={handleAddSlide}
                        className="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors"
                    >
                        ➕ Agregar
                    </button>
                </div>

                <div className="p-4">
                    {slides.length > 0 ? (
                        <div className="space-y-4">
                            {/* Preview del slide actual */}
                            <div className="relative bg-slate-100 rounded-lg overflow-hidden">
                                <div className="relative w-full">
                                    <div
                                        className="relative bg-slate-100 cursor-pointer group"
                                        onClick={() => handleImageClick('homeSlider', currentSlideIndex, 'imageUrl')}
                                    >
                                        {currentSlide.imageUrl ? (
                                            <>
                                                <img
                                                    src={currentSlide.imageUrl}
                                                    alt={currentSlide.title}
                                                    className="w-full h-48 object-cover"
                                                />
                                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <span className="text-3xl">📤</span>
                                                </div>
                                            </>
                                        ) : (
                                            <div className="h-48 flex items-center justify-center text-slate-400">
                                                <span className="text-4xl">📤</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Controles del slider */}
                                    {slides.length > 1 && (
                                        <>
                                            <button
                                                onClick={() => setCurrentSlideIndex(prev =>
                                                    prev === 0 ? slides.length - 1 : prev - 1
                                                )}
                                                className="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white px-2 py-1 rounded-full shadow-sm text-xl"
                                            >
                                                ◀️
                                            </button>
                                            <button
                                                onClick={() => setCurrentSlideIndex(prev =>
                                                    prev === slides.length - 1 ? 0 : prev + 1
                                                )}
                                                className="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white px-2 py-1 rounded-full shadow-sm text-xl"
                                            >
                                                ▶️
                                            </button>
                                        </>
                                    )}
                                </div>

                                {/* Info del slide */}
                                <div className="bg-white p-3 border-t border-slate-200">
                                    <p className="text-xs font-semibold text-slate-900">{currentSlide.title}</p>
                                    <p className="text-xs text-slate-600 line-clamp-2 mt-1">{currentSlide.subtitle}</p>
                                    {currentSlide.link && (
                                        <p className="text-xs text-blue-600 mt-1 truncate">{currentSlide.link}</p>
                                    )}
                                </div>
                            </div>

                            {/* Miniaturas de slides con controles */}
                            <div className="space-y-2">
                                {slides.map((slide, idx) => (
                                    <div
                                        key={idx}
                                        className={`flex items-center gap-2 p-2 rounded-lg border-2 transition-colors ${idx === currentSlideIndex
                                            ? 'border-blue-500 bg-blue-50'
                                            : 'border-slate-200 hover:border-slate-300 bg-white'
                                            }`}
                                    >
                                        {/* Thumbnail */}
                                        <div
                                            onClick={() => setCurrentSlideIndex(idx)}
                                            className="flex-shrink-0 cursor-pointer rounded overflow-hidden"
                                        >
                                            {slide.imageUrl ? (
                                                <img
                                                    src={slide.imageUrl}
                                                    alt="Slide"
                                                    className="w-16 h-10 object-cover"
                                                />
                                            ) : (
                                                <div className="w-16 h-10 bg-slate-200 flex items-center justify-center">
                                                    📤
                                                </div>
                                            )}
                                        </div>

                                        {/* Info */}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-xs font-semibold text-slate-900 line-clamp-1">{slide.title}</p>
                                            <p className="text-xs text-slate-600 line-clamp-1">{slide.subtitle}</p>
                                        </div>

                                        {/* Botones de acción */}
                                        <div className="flex gap-1 flex-shrink-0">
                                            {idx > 0 && (
                                                <button
                                                    onClick={() => handleMoveSlide(idx, 'up')}
                                                    className="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors"
                                                    title="Mover arriba"
                                                >
                                                    ⬆️
                                                </button>
                                            )}
                                            {idx < slides.length - 1 && (
                                                <button
                                                    onClick={() => handleMoveSlide(idx, 'down')}
                                                    className="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors"
                                                    title="Mover abajo"
                                                >
                                                    ⬇️
                                                </button>
                                            )}
                                            <button
                                                onClick={() => handleEditSlide(idx)}
                                                className="p-1 text-slate-600 hover:bg-slate-100 rounded transition-colors"
                                                title="Editar"
                                            >
                                                ✏️
                                            </button>
                                            <button
                                                onClick={() => handleDeleteSlide(idx)}
                                                className="p-1 text-red-600 hover:bg-red-100 rounded transition-colors"
                                                title="Eliminar"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <p className="text-sm text-slate-500">No hay slides configurados</p>
                        </div>
                    )}
                </div>
            </div>

            {/* MODAL PARA AGREGAR/EDITAR SLIDE */}
            {showSlideModal && slideForm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-lg max-w-md w-full max-h-96 overflow-y-auto p-6 space-y-4">
                        <h3 className="text-lg font-bold text-slate-900">
                            {editingSlideIndex !== null ? 'Editar Slide' : 'Agregar Nuevo Slide'}
                        </h3>

                        {/* Preview imagen */}
                        <div
                            onClick={() => slideImageInputRef.current?.click()}
                            className="relative h-40 bg-slate-100 rounded-lg overflow-hidden cursor-pointer group"
                        >
                            {slideForm.imageUrl ? (
                                <img
                                    src={slideForm.imageUrl}
                                    alt="Slide"
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

                        <input
                            ref={slideImageInputRef}
                            type="file"
                            accept="image/*"
                            onChange={handleSlideImageUpload}
                            className="hidden"
                        />

                        {/* Imagen Mobile (Opcional) */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-2">
                                📱 Imagen Móvil (Opcional)
                            </label>
                            <div
                                onClick={() => slideImageMobileInputRef.current?.click()}
                                className="relative h-32 bg-slate-100 rounded-lg overflow-hidden cursor-pointer group"
                            >
                                {slideForm.imageUrlMobile ? (
                                    <img
                                        src={slideForm.imageUrlMobile}
                                        alt="Slide Mobile"
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center text-2xl opacity-50">
                                        📱
                                    </div>
                                )}
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <span className="text-2xl">📤</span>
                                </div>
                            </div>
                        </div>

                        <input
                            ref={slideImageMobileInputRef}
                            type="file"
                            accept="image/*"
                            onChange={handleSlideImageUploadMobile}
                            className="hidden"
                        />

                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Título
                            </label>
                            <input
                                type="text"
                                value={slideForm.title || ''}
                                onChange={(e) => setSlideForm({ ...slideForm, title: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                placeholder="Ej: Visita Blog MindMeet"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Subtítulo
                            </label>
                            <input
                                type="text"
                                value={slideForm.subtitle || ''}
                                onChange={(e) => setSlideForm({ ...slideForm, subtitle: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                placeholder="Ej: Mantente informado..."
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-2">
                                Link (URL)
                            </label>
                            <input
                                type="text"
                                value={slideForm.link || ''}
                                onChange={(e) => setSlideForm({ ...slideForm, link: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono text-xs"
                                placeholder="https://..."
                            />
                        </div>

                        <div className="flex gap-2 pt-4">
                            <button
                                onClick={handleCancelSlideModal}
                                className="flex-1 px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-900 rounded-lg font-medium transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleSaveSlide}
                                className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
                            >
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* PROMOTIONS SECTION */}
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 bg-slate-50 p-4">
                    <h3 className="font-semibold text-slate-900">Promociones</h3>
                    <p className="text-xs text-slate-500 mt-1">Haz clic en las imágenes para cambiarlas</p>
                </div>

                <div className="p-4">
                    {promotions.length > 0 ? (
                        <div className="grid grid-cols-3 gap-4">
                            {promotions.map((promotion, idx) => (
                                <div
                                    key={idx}
                                    className="relative rounded-lg overflow-hidden bg-slate-100 cursor-pointer group"
                                    onClick={() => handleImageClick('promotions', idx)}
                                >
                                    {promotion.url ? (
                                        <>
                                            <img
                                                src={promotion.url}
                                                alt={promotion.alt || 'Promoción'}
                                                className="w-full aspect-video object-cover"
                                            />
                                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                <span className="text-3xl">📤</span>
                                            </div>
                                        </>
                                    ) : (
                                        <div className="w-full aspect-video flex items-center justify-center text-4xl">
                                            📤
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <p className="text-sm text-slate-500">No hay promociones configuradas</p>
                        </div>
                    )}
                </div>
            </div>

            {/* SECTIONS / SLIDERS DE PROFESIONALES */}
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 bg-slate-50 p-4">
                    <h3 className="font-semibold text-slate-900">Especialidades Más Buscadas</h3>
                    <p className="text-xs text-slate-500 mt-1">Haz clic en las tarjetas para editar imagen y título</p>
                </div>

                <div className="p-4">
                    <EspecialidadesEditor data={data} onChange={onChange} />
                </div>
            </div>

            {/* SECTIONS / SLIDERS DE PROFESIONALES */}
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 bg-slate-50 p-4">
                    <h3 className="font-semibold text-slate-900">Bloques / Sliders del Home</h3>
                    <p className="text-xs text-slate-500 mt-1">Haz clic para editar título, emoji y cantidad de profesionales</p>
                </div>

                <div className="p-4">
                    <SectionsEditor data={data} onChange={onChange} />
                </div>
            </div>

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
