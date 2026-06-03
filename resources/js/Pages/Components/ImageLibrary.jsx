import { useState } from 'react';

export default function ImageLibrary({ uploadedImages = {}, onSelectImage, title = "Historial de Imágenes", showRecent = true }) {
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [selectedImage, setSelectedImage] = useState(null);
    
    const recentImages = (uploadedImages.recent || []);
    
    // Agrupar imágenes por categoría
    const categorized = {
        all: recentImages,
        promotions: recentImages.filter(img => img.category === 'promotions'),
        hero: recentImages.filter(img => img.category === 'hero'),
        slider: recentImages.filter(img => img.category === 'slider'),
        especialidades: recentImages.filter(img => img.category === 'especialidades'),
    };

    const filteredImages = categorized[selectedCategory] || [];

    const handleSelectImage = (image) => {
        setSelectedImage(image.url);
        onSelectImage(image.url);
        
        // Mostrar feedback visual
        setTimeout(() => setSelectedImage(null), 1000);
    };

    if (!showRecent) {
        return null;
    }

    return (
        <div className="rounded-lg overflow-hidden border-2 border-dashed border-blue-300 bg-blue-50">
            {title && (
                <div className="bg-blue-100 p-3 border-b border-blue-200">
                    <h4 className="font-semibold text-blue-900">{title}</h4>
                    <p className="text-xs text-blue-700 mt-0.5">
                        {recentImages.length === 0 ? 'Sube imágenes para verlas aquí' : `${recentImages.length} imágenes en el historial`}
                    </p>
                </div>
            )}

            {/* Filtros de categoría */}
            {recentImages.length > 0 && (
                <div className="border-b border-blue-200 p-3 flex gap-2 bg-white overflow-x-auto">
                    {['all', 'promotions', 'hero', 'slider', 'especialidades'].map(cat => {
                        const count = categorized[cat].length;
                        if (cat !== 'all' && count === 0) return null;
                        
                        return (
                            <button
                                key={cat}
                                onClick={() => setSelectedCategory(cat)}
                                className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap ${
                                    selectedCategory === cat
                                        ? 'bg-blue-600 text-white shadow-md'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {cat === 'all' ? '📌 Todas' : 
                                 cat === 'promotions' ? '📢 Promociones' :
                                 cat === 'hero' ? '🎯 Hero' :
                                 cat === 'slider' ? '🎠 Slider' :
                                 '❤️ Especialidades'}
                                {cat !== 'all' && <span className="ml-1 text-xs">({count})</span>}
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Grid de imágenes */}
            <div className="p-4">
                {filteredImages.length > 0 ? (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        {filteredImages.map((image, idx) => (
                            <button
                                key={idx}
                                onClick={() => handleSelectImage(image)}
                                className={`relative group rounded-lg overflow-hidden bg-slate-100 aspect-square transition-all ${
                                    selectedImage === image.url
                                        ? 'ring-4 ring-green-400 scale-95'
                                        : 'hover:ring-2 hover:ring-blue-400'
                                }`}
                            >
                                <img
                                    src={image.url}
                                    alt="Imagen del historial"
                                    className="w-full h-full object-cover group-hover:scale-110 transition-transform"
                                />
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <span className="text-2xl">✓</span>
                                </div>
                                <div className="absolute bottom-1 right-1 bg-slate-900/70 text-white text-xs rounded px-1.5 py-0.5">
                                    {new Date(image.uploadedAt).toLocaleDateString('es-MX', {
                                        month: 'short',
                                        day: 'numeric'
                                    })}
                                </div>
                                {selectedImage === image.url && (
                                    <div className="absolute inset-0 flex items-center justify-center bg-green-500/30 animate-pulse">
                                        <span className="text-3xl">✓</span>
                                    </div>
                                )}
                            </button>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <span className="text-5xl mb-3 block">📷</span>
                        <p className="text-sm text-blue-600 font-medium">
                            {recentImages.length === 0 
                                ? 'Sin imágenes aún. ¡Sube una para empezar!' 
                                : 'Sin imágenes en esta categoría'}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
