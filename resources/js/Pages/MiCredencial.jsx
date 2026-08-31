import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { ArrowDownTrayIcon, CameraIcon, PrinterIcon } from '@heroicons/react/24/outline';
import { useEffect, useRef, useState } from 'react';

const mindmeetLogo = 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764650408/MindMeet_1280_x_350_px_c6yojr.png';

function initials(name = '') {
    return name.split(' ').filter(Boolean).slice(0, 2).map((word) => word[0]).join('').toUpperCase() || 'MM';
}

function savePdf(blob) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'credencial-mindmeet-superadmin.pdf';
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export default function MiCredencial({ auth }) {
    const [credential, setCredential] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const photoInput = useRef(null);

    useEffect(() => {
        window.axios.get('/admin/api/credential')
            .then(({ data }) => setCredential(data))
            .catch(() => setError('No pudimos cargar tu credencial. Intenta nuevamente.'));
    }, []);

    const downloadPdf = async () => {
        try {
            setLoading(true);
            const { data } = await window.axios.get('/admin/api/credential/pdf', { responseType: 'blob' });
            savePdf(data);
        } catch {
            setError('No pudimos generar el PDF. Intenta nuevamente.');
        } finally {
            setLoading(false);
        }
    };

    const printPdf = async () => {
        let popup;

        try {
            popup = window.open('', '_blank');
            if (!popup) throw new Error('popup_blocked');

            setLoading(true);
            const { data } = await window.axios.get('/admin/api/credential/pdf', { responseType: 'blob' });
            const url = URL.createObjectURL(data);
            popup.location.href = url;
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch {
            popup?.close();
            setError('No pudimos abrir el PDF. Revisa que el navegador permita ventanas emergentes.');
        } finally {
            setLoading(false);
        }
    };

    const uploadPhoto = async (event) => {
        const photo = event.target.files?.[0];
        event.target.value = '';
        if (!photo) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(photo.type) || photo.size > 5 * 1024 * 1024) {
            setError('Selecciona una imagen JPG, PNG o WebP de máximo 5 MB.');
            return;
        }

        try {
            setError('');
            setUploading(true);
            const formData = new FormData();
            formData.append('photo', photo);
            const { data } = await window.axios.post('/admin/api/credential/photo', formData);
            setCredential(data);
        } catch (uploadError) {
            setError(uploadError.response?.data?.message || 'No pudimos subir tu foto. Intenta nuevamente.');
        } finally {
            setUploading(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<div><p className="text-xs font-black uppercase tracking-[0.28em] text-sky-700">Cuenta MindMeet</p><h1 className="mt-1 text-2xl font-black text-slate-950">Mi credencial</h1><p className="mt-1 text-sm text-slate-500">Tu identificación digital como superadmin de MindMeet.</p></div>}
        >
            <Head title="Mi credencial" />
            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-5xl">
                    {error ? <div className="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">{error}</div> : null}
                    {!credential ? <div className="h-[520px] w-[340px] max-w-full animate-pulse rounded-[28px] bg-sky-100" /> : (
                        <div className="flex flex-col items-center gap-6">
                            <article className="relative h-[520px] w-[340px] max-w-full overflow-hidden rounded-[28px] bg-white px-7 pb-7 shadow-xl shadow-slate-300/60">
                                <div className="-mx-7 h-11 bg-gradient-to-r from-[#1358A9] via-[#3FC0E8] to-[#00C4B8]" />
                                <div className="absolute -left-16 top-36 h-48 w-32 rounded-full border border-sky-100" />
                                <div className="absolute -right-16 bottom-32 h-48 w-32 rounded-full border border-sky-100" />
                                <img src={mindmeetLogo} alt="MindMeet" className="relative mx-auto mt-7 h-16 w-auto" />
                                <div className="relative mx-auto mt-5 flex h-40 w-40 items-center justify-center overflow-hidden rounded-full border border-sky-100 bg-[#EAF8FE] text-4xl font-black text-[#1358A9]">
                                    {credential.photoUrl ? <img src={credential.photoUrl} alt={`Fotografía de ${credential.fullName}`} className="h-full w-full object-cover" /> : initials(credential.fullName)}
                                    <button type="button" onClick={() => photoInput.current?.click()} disabled={uploading} className="absolute bottom-2 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1.5 text-[10px] font-bold text-[#1358A9] shadow-sm transition hover:bg-white disabled:opacity-60"><CameraIcon className="h-3.5 w-3.5" />{uploading ? 'Subiendo...' : 'Cambiar foto'}</button>
                                </div>
                                <input ref={photoInput} type="file" accept="image/jpeg,image/png,image/webp" onChange={uploadPhoto} className="hidden" />
                                <h2 className="relative mt-6 text-center text-2xl font-bold leading-tight text-[#12304A]">{credential.fullName}</h2>
                                <p className="relative mx-auto mt-4 w-fit rounded-full bg-[#1358A9] px-7 py-2 text-sm font-bold tracking-[.16em] text-white">SUPERADMIN</p>
                                <div className="relative mt-7 border-t border-[#BDEBFF] pt-5 text-center text-sm font-semibold text-[#12304A]"><span className="text-[#00A99D]">✓ Cuenta verificada</span><span className="mx-2 text-slate-300">·</span>ID: {credential.credentialId}</div>
                            </article>
                            <div className="flex flex-wrap justify-center gap-3">
                                <button type="button" onClick={downloadPdf} disabled={loading} className="inline-flex items-center gap-2 rounded-xl bg-[#1358A9] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#0d477f] disabled:opacity-60"><ArrowDownTrayIcon className="h-5 w-5" />{loading ? 'Generando...' : 'Descargar PDF'}</button>
                                <button type="button" onClick={printPdf} disabled={loading} className="inline-flex items-center gap-2 rounded-xl border border-[#1358A9] px-4 py-3 text-sm font-bold text-[#1358A9] transition hover:bg-sky-50 disabled:opacity-60"><PrinterIcon className="h-5 w-5" />Imprimir</button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
