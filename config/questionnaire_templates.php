<?php

return [
    [
        'slug' => 'entrevista-inicial',
        'title' => 'Entrevista clínica inicial',
        'description' => 'Plantilla general para conocer el motivo de consulta y el contexto inicial.',
        'structure' => [
            ['id' => 'section_initial_context', 'type' => 'section', 'title' => 'Motivo de consulta', 'description' => '', 'required' => false],
            ['id' => 'q_initial_reason', 'type' => 'long_text', 'title' => '¿Qué te trae a consulta en este momento?', 'description' => '', 'required' => true],
            ['id' => 'q_initial_expectations', 'type' => 'long_text', 'title' => '¿Qué esperas obtener de este proceso?', 'description' => '', 'required' => false],
            ['id' => 'section_initial_history', 'type' => 'section', 'title' => 'Antecedentes relevantes', 'description' => '', 'required' => false],
            ['id' => 'q_initial_support', 'type' => 'long_text', 'title' => '¿Con qué personas o redes de apoyo cuentas actualmente?', 'description' => '', 'required' => false],
        ],
    ],
    [
        'slug' => 'seguimiento-semanal',
        'title' => 'Seguimiento semanal',
        'description' => 'Plantilla breve para revisar cambios y prioridades entre sesiones.',
        'structure' => [
            ['id' => 'section_weekly_state', 'type' => 'section', 'title' => 'Semana actual', 'description' => '', 'required' => false],
            ['id' => 'q_weekly_state', 'type' => 'linear_scale', 'title' => 'En una escala de 0 a 10, ¿cómo valorarías tu bienestar esta semana?', 'description' => '', 'required' => true, 'scale' => ['min' => 0, 'max' => 10, 'minLabel' => 'Muy bajo', 'maxLabel' => 'Muy alto']],
            ['id' => 'q_weekly_changes', 'type' => 'long_text', 'title' => '¿Qué cambios importantes notaste desde la última sesión?', 'description' => '', 'required' => false],
            ['id' => 'q_weekly_focus', 'type' => 'long_text', 'title' => '¿Qué te gustaría trabajar en la próxima sesión?', 'description' => '', 'required' => false],
        ],
    ],
    [
        'slug' => 'registro-bienestar',
        'title' => 'Registro de bienestar',
        'description' => 'Plantilla de autoobservación para acompañar el seguimiento clínico.',
        'structure' => [
            ['id' => 'section_wellbeing', 'type' => 'section', 'title' => 'Autoobservación', 'description' => '', 'required' => false],
            ['id' => 'q_wellbeing_emotions', 'type' => 'checkboxes', 'title' => '¿Qué emociones estuvieron más presentes?', 'description' => '', 'required' => false, 'options' => [['id' => 'opt_calm', 'label' => 'Calma'], ['id' => 'opt_joy', 'label' => 'Alegría'], ['id' => 'opt_worry', 'label' => 'Preocupación'], ['id' => 'opt_sadness', 'label' => 'Tristeza']]],
            ['id' => 'q_wellbeing_notes', 'type' => 'long_text', 'title' => 'Describe una situación significativa de los últimos días.', 'description' => '', 'required' => false],
        ],
    ],
];
