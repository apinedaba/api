import { useEffect, useState } from 'react';

export default function Toast({ message, type = 'info', duration = 3000, onClose }) {
    const [isVisible, setIsVisible] = useState(true);

    useEffect(() => {
        if (duration > 0) {
            const timer = setTimeout(() => {
                setIsVisible(false);
                onClose?.();
            }, duration);

            return () => clearTimeout(timer);
        }
    }, [duration, onClose]);

    if (!isVisible) return null;

    const styles = {
        success: 'bg-green-50 border-green-200 text-green-700',
        error: 'bg-red-50 border-red-200 text-red-700',
        info: 'bg-blue-50 border-blue-200 text-blue-700',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-700',
    };

    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️',
    };

    return (
        <div className={`fixed top-4 right-4 max-w-md p-4 rounded-lg border ${styles[type]} shadow-lg z-50 flex items-start gap-3`}>
            <span className="text-lg flex-shrink-0">{icons[type]}</span>
            <div className="flex-1">
                <p className="text-sm font-medium">{message}</p>
            </div>
            <button
                onClick={() => {
                    setIsVisible(false);
                    onClose?.();
                }}
                className="text-lg flex-shrink-0 hover:opacity-70"
            >
                ✕
            </button>
        </div>
    );
}

export function useToast() {
    const [toasts, setToasts] = useState([]);

    const addToast = (message, type = 'info', duration = 3000) => {
        const id = Date.now();
        setToasts(prev => [...prev, { id, message, type, duration }]);
        return id;
    };

    const removeToast = (id) => {
        setToasts(prev => prev.filter(toast => toast.id !== id));
    };

    return { toasts, addToast, removeToast };
}
