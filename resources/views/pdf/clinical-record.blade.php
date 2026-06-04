<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Expediente clinico - {{ $patient->name }}</title>
    <style>
        @page { size: A4; margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            background: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.48;
        }
        h1, h2, h3, p { margin: 0; }
        .page { min-height: 100%; }
        .cover {
            min-height: 260mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #dce7f3;
            padding: 28px;
        }
        .topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
        .logo { width: 200px; height: 80px; object-fit: contain; object-position: left center; }
        .doc-label { color: #0f9ec2; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .title { margin-top: 56px; max-width: 520px; font-size: 34px; line-height: 1.08; color: #111827; }
        .subtitle { margin-top: 14px; color: #5b667a; font-size: 14px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 36px; }
        .meta-card { border: 1px solid #dce7f3; border-radius: 10px; padding: 14px; background: #f8fbfd; }
        .label { color: #6b7280; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .value { margin-top: 4px; color: #111827; font-size: 13px; font-weight: 700; }
        .section { margin-top: 18px; page-break-inside: avoid; }
        .section.break-before { page-break-before: always; }
        .section-title {
            border-bottom: 2px solid #0f9ec2;
            color: #111827;
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 10px;
            padding-bottom: 6px;
        }
        .subsection-title { color: #0f9ec2; font-size: 12px; font-weight: 800; margin: 12px 0 6px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .box { border: 1px solid #e5edf5; border-radius: 8px; padding: 12px; page-break-inside: avoid; }
        .muted { color: #667085; }
        .rich { color: #283548; }
        .rich p { margin-bottom: 8px; }
        .rich ul, .rich ol { margin: 6px 0 8px 18px; padding: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; page-break-inside: avoid; }
        th { background: #f1f7fb; color: #344054; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #dce7f3; padding: 8px; vertical-align: top; }
        .session { border-left: 4px solid #0f9ec2; margin-bottom: 10px; }
        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 36px; margin-top: 56px; }
        .signature { border-top: 1px solid #9aa4b2; padding-top: 8px; text-align: center; }
        .footer-note { color: #667085; font-size: 10px; }
        .pill { display: inline-block; border-radius: 999px; background: #e6f7fb; color: #087c96; font-size: 10px; font-weight: 700; padding: 3px 8px; }
    </style>
</head>
<body>
@php
    $school = $professionalSchool ?: [];
    $contact = $patient->contacto ?: [];
    $address = $patient->address ?: [];
    $relationships = is_array($patient->relationships ?? null) ? $patient->relationships : [];
    $age = data_get($patient->relevantes, 'fechaNac') ? \Carbon\Carbon::parse(data_get($patient->relevantes, 'fechaNac'))->age . ' anos' : 'No registrada';
    $gender = data_get($patient->relevantes, 'genero') ?: data_get($patient->relevantes, 'sexo') ?: 'No registrado';
    $normalize = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
    $html = fn ($value) => $value ? preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $normalize($value)) : '<p>Sin informacion registrada.</p>';
    $plain = fn ($value) => trim(strip_tags($normalize($value))) ?: 'Sin informacion registrada.';
    $score = function ($scale) {
        $items = data_get($scale, 'items', []);
        if (!is_array($items)) return 0;
        return collect($items)->sum(fn ($item) => (int) data_get($item, 'value', 0));
    };
@endphp

<main class="page">
    <section class="cover">
        <div>
            <div class="topbar">
                <img class="logo" src="{{ $logoUrl }}" alt="Logo">
                <div style="text-align:right">
                    <p class="doc-label">Expediente clinico</p>
                    <p class="muted">Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            <h1 class="title">Expediente clinico psicologico</h1>
            <p class="subtitle">Documento clinico de consulta profesional. La informacion se integra desde el expediente, sesiones, instrumentos y registros del paciente.</p>

            <div class="meta-grid">
                <div class="meta-card">
                    <p class="label">Paciente</p>
                    <p class="value">{{ $patient->name ?: 'Paciente sin nombre' }}</p>
                    <p class="muted">ID PAC-{{ str_pad((string) $patient->id, 4, '0', STR_PAD_LEFT) }} · {{ $age }} · {{ $gender }}</p>
                </div>
                <div class="meta-card">
                    <p class="label">Profesional responsable</p>
                    <p class="value">{{ $user->name ?: 'No registrado' }}</p>
                    <p class="muted">{{ data_get($school, 'profesion', 'Titulo no registrado') }} · Cedula {{ data_get($school, 'cedula', 'no registrada') }}</p>
                </div>
                <div class="meta-card">
                    <p class="label">Sesiones</p>
                    <p class="value">{{ $sessions->count() }}</p>
                    <p class="muted">Primera: {{ optional($sessions->last()?->start)->format('d/m/Y') ?: 'Sin registro' }} · Ultima: {{ optional($sessions->first()?->start)->format('d/m/Y') ?: 'Sin registro' }}</p>
                </div>
                <div class="meta-card">
                    <p class="label">Estado del vinculo</p>
                    <p class="value">{{ $relation->status ?: 'Vinculado' }}</p>
                    <p class="muted">{{ $relation->archived_at ? 'Paciente archivado' : 'Paciente activo' }}</p>
                </div>
            </div>
        </div>

        <p class="footer-note">Documento confidencial. Su uso, reproduccion o divulgacion queda sujeto a la autorizacion del paciente y del profesional responsable.</p>
    </section>

    <section class="section break-before">
        <h2 class="section-title">Identificacion y contacto</h2>
        <div class="grid-2">
            <div class="box">
                <p><strong>Nombre:</strong> {{ $patient->name ?: 'No registrado' }}</p>
                <p><strong>Correo:</strong> {{ $patient->email ?: 'No registrado' }}</p>
                <p><strong>Telefono:</strong> {{ data_get($contact, 'telefono', $patient->phone ?: 'No registrado') }}</p>
                <p><strong>Ocupacion:</strong> {{ data_get($patient->relevantes, 'ocupacion', 'No registrada') }}</p>
                <p><strong>Estado civil:</strong> {{ data_get($patient->relevantes, 'estadoCivil', 'No registrado') }}</p>
            </div>
            <div class="box">
                <p><strong>CP:</strong> {{ data_get($address, 'cp', 'No registrado') }}</p>
                <p><strong>Calle:</strong> {{ data_get($address, 'calle', 'No registrada') }}</p>
                <p><strong>Colonia:</strong> {{ data_get($address, 'colonia', 'No registrada') }}</p>
                <p><strong>Municipio:</strong> {{ data_get($address, 'municipio', 'No registrado') }}</p>
                <p><strong>Estado:</strong> {{ data_get($address, 'estado', 'No registrado') }}</p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Aviso legal y confidencialidad</h2>
        <div class="box">
            <p>El presente documento constituye parte del expediente clinico psicologico del paciente, elaborado conforme a la Norma Oficial Mexicana NOM-004-SSA3-2012, del Expediente Clinico.</p>
            <p class="muted" style="margin-top:8px">La informacion aqui contenida es confidencial, de uso exclusivo para fines clinicos y profesionales, y se encuentra protegida por las disposiciones aplicables en materia de proteccion de datos personales y secreto profesional.</p>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Motivo de consulta</h2>
        <div class="box rich">{!! $html($expediente?->motivoConsulta ?: $patient->historial) !!}</div>
    </section>

    <section class="section">
        <h2 class="section-title">Relaciones y contactos</h2>
        @if(count($relationships))
            <table>
                <thead><tr><th>Nombre</th><th>Relacion</th><th>Contacto</th><th>Dinamica</th></tr></thead>
                <tbody>
                @foreach($relationships as $relationship)
                    <tr>
                        <td>{{ data_get($relationship, 'nombre', 'No registrado') }} {!! data_get($relationship, 'es_contacto_emergencia') ? '<br><span class="pill">Emergencia</span>' : '' !!}</td>
                        <td>{{ data_get($relationship, 'parentesco', 'No registrado') }}</td>
                        <td>{{ data_get($relationship, 'telefono', data_get($relationship, 'whatsapp', data_get($relationship, 'correo', 'No registrado'))) }}</td>
                        <td>{{ data_get($relationship, 'dinamica_familiar', data_get($relationship, 'descripcion_relacion', 'Sin descripcion')) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No hay contactos o relaciones registradas.</p>
        @endif
    </section>

    <section class="section break-before">
        <h2 class="section-title">Antecedentes y evaluacion clinica</h2>
        <h3 class="subsection-title">Antecedentes personales y heredofamiliares</h3>
        @if(is_array($expediente?->antecedentes) && count($expediente->antecedentes))
            <table>
                <thead><tr><th>Antecedente</th><th>Familiar</th><th>Observaciones</th></tr></thead>
                <tbody>
                @foreach($expediente->antecedentes as $item)
                    <tr><td>{{ data_get($item, 'enfermedad') }}</td><td>{{ data_get($item, 'familiar') }}</td><td>{{ data_get($item, 'observaciones') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No hay antecedentes registrados.</p>
        @endif

        <h3 class="subsection-title">Examen mental</h3>
        @if(is_array($expediente?->examen_mental) && count($expediente->examen_mental))
            @foreach($expediente->examen_mental as $key => $value)
                @if(filled($value))
                    <div class="box" style="margin-bottom:8px">
                        <strong>{{ $mentalLabels[$key] ?? $key }}</strong>
                        <p class="muted">{{ $value }}</p>
                    </div>
                @endif
            @endforeach
        @else
            <p class="muted">No hay examen mental registrado.</p>
        @endif

        <h3 class="subsection-title">Linea de vida</h3>
        @if(is_array($expediente?->linea_vida) && count($expediente->linea_vida))
            <table>
                <thead><tr><th>Etapa</th><th>Edad</th><th>Descripcion</th></tr></thead>
                <tbody>
                @foreach($expediente->linea_vida as $item)
                    <tr><td>{{ data_get($item, 'stage') }}</td><td>{{ data_get($item, 'age') }}</td><td>{{ data_get($item, 'description') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No hay eventos de linea de vida registrados.</p>
        @endif
    </section>

    <section class="section break-before">
        <h2 class="section-title">Diagnostico y plan de tratamiento</h2>
        <h3 class="subsection-title">Diagnostico general</h3>
        <div class="box rich">{!! $html($expediente?->diagnostico) !!}</div>
        <h3 class="subsection-title">Plan de tratamiento</h3>
        <div class="box rich">{!! $html($expediente?->plan_tratamiento) !!}</div>
        <h3 class="subsection-title">Dinamica familiar</h3>
        <div class="box rich">{!! $html($expediente?->dinamicaFamiliar) !!}</div>
        <h3 class="subsection-title">Vida social</h3>
        <div class="box rich">{!! $html($expediente?->vidaSocial) !!}</div>
    </section>

    <section class="section">
        <h2 class="section-title">Escalas psicometricas</h2>
        @if(is_array($expediente?->escalas) && count($expediente->escalas))
            @foreach($expediente->escalas as $scale)
                <div class="box" style="margin-bottom:10px">
                    <p><strong>{{ data_get($scale, 'label', 'Escala') }}</strong> · {{ data_get($scale, 'reference', 'Referencia no registrada') }}</p>
                    <p class="muted">Puntaje total: <strong>{{ $score($scale) }}</strong></p>
                    @if(is_array(data_get($scale, 'items')))
                        <table>
                            <tbody>
                            @foreach(data_get($scale, 'items', []) as $item)
                                <tr><td>{{ data_get($item, 'label') }}</td><td style="width:80px">{{ data_get($item, 'value', 0) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        @else
            <p class="muted">No hay escalas registradas.</p>
        @endif
    </section>

    <section class="section break-before">
        <h2 class="section-title">Medicamentos y sintomas</h2>
        <div class="grid-2">
            <div class="box">
                <h3 class="subsection-title" style="margin-top:0">Medicamentos</h3>
                @forelse($medications as $medication)
                    <p><strong>{{ $medication->medication_name }}</strong> · {{ $medication->dosage ?: 'Sin dosis' }}</p>
                    <p class="muted">{{ $medication->frequency ?: 'Sin frecuencia' }} · {{ $medication->start_date ?: 'Sin fecha' }} - {{ $medication->end_date ?: 'Sin fecha' }}</p>
                @empty
                    <p class="muted">No hay medicamentos registrados.</p>
                @endforelse
            </div>
            <div class="box">
                <h3 class="subsection-title" style="margin-top:0">Sintomas</h3>
                @forelse($symptoms as $symptom)
                    <p><strong>{{ data_get($symptom, 'sintoma', 'Sintoma') }}</strong></p>
                    <p class="muted">{{ data_get($symptom, 'fecha', 'Sin fecha') }}</p>
                @empty
                    <p class="muted">No hay sintomas registrados.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section break-before">
        <h2 class="section-title">Historial clinico por sesiones</h2>
        @forelse($sessions as $index => $session)
            <div class="box session">
                <p><strong>{{ $session->title ?: 'Sesion ' . ($sessions->count() - $index) }}</strong></p>
                <p class="muted">{{ optional($session->start)->format('d/m/Y H:i') ?: 'Sin fecha' }} · {{ $session->state ?: $session->statusPatient ?: 'Estado no definido' }}</p>
                @if($session->comments)
                    <p style="margin-top:8px">{{ $plain($session->comments) }}</p>
                @endif
                @if($session->notes->count())
                    <h3 class="subsection-title">Notas</h3>
                    @foreach($session->notes as $note)
                        <p><strong>{{ $note->type ?: 'Nota' }}:</strong> {{ $plain($note->content) }}</p>
                    @endforeach
                @else
                    <p class="muted" style="margin-top:8px">Sin notas registradas.</p>
                @endif
                <p class="muted" style="margin-top:8px">Adjuntos: {{ $session->attachments->count() }}</p>
            </div>
        @empty
            <p class="muted">No hay sesiones registradas.</p>
        @endforelse
    </section>

    <section class="section break-before">
        <h2 class="section-title">Consentimiento y firmas</h2>
        <div class="box">
            <p>El paciente manifiesta haber sido informado sobre el proceso de evaluacion y atencion psicologica, asi como sobre el manejo confidencial de su informacion clinica.</p>
            <p class="muted" style="margin-top:8px">Estado: {{ data_get($patient->consentimiento, 'status') === 'signed' ? 'Firmado digitalmente' : (data_get($patient->consentimiento, 'status') === 'uploaded' ? 'Consentimiento escaneado cargado' : 'Consentimiento fisico registrado') }}</p>
            <p class="muted" style="margin-top:8px">Este documento puede actualizarse conforme a la evolucion clinica del paciente y al criterio profesional responsable.</p>
            @php($signatureSource = data_get($patient->consentimiento, 'signature_data_url') ?: data_get($patient->consentimiento, 'signature_url'))
            @if($signatureSource)
                <div style="margin-top:14px">
                    <p class="muted">Firma digital del paciente</p>
                    <img src="{{ $signatureSource }}" alt="Firma digital" style="max-width:260px; max-height:90px; border:1px solid #e5e7eb; padding:8px">
                </div>
            @endif
        </div>
        <div class="signature-grid">
            <div class="signature">
                <strong>{{ $patient->name ?: 'Paciente' }}</strong>
                <p class="muted">Paciente</p>
            </div>
            <div class="signature">
                <strong>{{ $user->name ?: 'Profesional responsable' }}</strong>
                <p class="muted">{{ data_get($school, 'profesion', 'Profesional') }}{{ data_get($school, 'cedula') ? ' · Cedula ' . data_get($school, 'cedula') : '' }}</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
