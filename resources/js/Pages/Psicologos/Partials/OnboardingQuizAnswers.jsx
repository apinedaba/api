const expectationLabels = {
    more_patients: 'Más pacientes',
    organize_practice: 'Organizar mejor mi consultorio',
    explore: 'Ver qué es',
    connect_psychologists: 'Conectar con otros psicólogos',
    other: 'Otro',
};

const sourceLabels = {
    meta: 'Facebook / Instagram (Meta)',
    colleague: 'Recomendación de un colega',
    tiktok: 'TikTok',
    google: 'Google',
    other: 'Otro',
};

export default function OnboardingQuizAnswers({ psychologist }) {
    const quiz = psychologist?.configurations?.onboarding_quiz;

    const expectation = quiz?.expectation === 'other'
        ? quiz?.expectation_other
        : expectationLabels[quiz?.expectation];
    const source = quiz?.source === 'other'
        ? quiz?.source_other
        : sourceLabels[quiz?.source];

    return (
        <section>
            <p className="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Quiz de bienvenida</p>
            <h3 className="mt-1 text-xl font-black text-slate-950">Expectativas y procedencia</h3>

            {quiz?.expectation && quiz?.source ? (
                <dl className="mt-5 grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">¿Qué espera de MindMeet?</dt>
                        <dd className="mt-2 text-sm font-semibold text-slate-900">{expectation || 'Sin detalle'}</dd>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">¿Cómo conoció MindMeet?</dt>
                        <dd className="mt-2 text-sm font-semibold text-slate-900">{source || 'Sin detalle'}</dd>
                    </div>
                </dl>
            ) : (
                <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    Este psicólogo todavía no ha respondido el quiz.
                </p>
            )}
        </section>
    );
}
