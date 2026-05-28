<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_center_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category_key', 60);
            $table->string('category_name', 120);
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('estimated_read_minutes')->default(4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('help_center_articles')->insert([
            [
                'title' => 'Crear cuenta',
                'slug' => 'crear-cuenta',
                'category_key' => 'primeros-pasos',
                'category_name' => 'Primeros pasos',
                'summary' => 'Lo esencial para abrir tu cuenta profesional en MindMeet sin perder tiempo.',
                'body' => "1. Registra tu cuenta con tu correo profesional o el que uses para operar tu agenda.\n2. Verifica tu correo cuando MindMeet te lo solicite.\n3. Guarda una contraseña segura y activa tus datos básicos desde tu perfil.\n\nRecomendaciones:\n- Usa un nombre claro y consistente con tu práctica.\n- Ten a la mano tu información profesional y de contacto.\n- Si te atoraste en el acceso o registro, abre WhatsApp y cuéntanos qué pantalla estás viendo.",
                'estimated_read_minutes' => 3,
                'sort_order' => 10,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Completar perfil',
                'slug' => 'completar-perfil',
                'category_key' => 'primeros-pasos',
                'category_name' => 'Primeros pasos',
                'summary' => 'Cómo dejar tu perfil listo para transmitir confianza y evitar bloqueos de visibilidad.',
                'body' => "Tu perfil debe comunicar profesionalismo desde el primer vistazo.\n\nChecklist recomendado:\n- Foto profesional y reciente.\n- Nombre público claro.\n- Enfoque terapéutico y especialidades bien definidas.\n- Descripción breve, humana y fácil de entender.\n- Experiencia, idiomas y modalidades de sesión.\n- Datos de contacto y consultorio, si aplican.\n\nTip MindMeet:\nLos perfiles completos convierten mejor porque el paciente entiende más rápido si eres la persona indicada.",
                'estimated_read_minutes' => 4,
                'sort_order' => 20,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Publicarse',
                'slug' => 'publicarse',
                'category_key' => 'primeros-pasos',
                'category_name' => 'Primeros pasos',
                'summary' => 'Qué necesitas para aparecer visible dentro del catálogo público de MindMeet.',
                'body' => "Para publicarte necesitas cumplir con los puntos que habilitan visibilidad pública.\n\nNormalmente debes tener:\n- Cuenta activa.\n- Perfil completo.\n- Verificación de identidad aprobada.\n- Suscripción activa, periodo de prueba real o acceso vitalicio.\n\nSi ya completaste tu perfil y sigues sin aparecer, revisa:\n- Estado de suscripción.\n- Verificación pendiente.\n- Información faltante en perfil o configuraciones de sesión.",
                'estimated_read_minutes' => 4,
                'sort_order' => 30,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Cómo aparecer en búsquedas',
                'slug' => 'como-aparecer-en-busquedas',
                'category_key' => 'activar-pacientes',
                'category_name' => 'Activar pacientes',
                'summary' => 'Factores que ayudan a que tu perfil se encuentre más fácil dentro del catálogo.',
                'body' => "Aparecer en búsquedas no depende de un solo factor. MindMeet prioriza perfiles útiles para el paciente.\n\nMejora esto:\n- Especialidades bien configuradas.\n- Enfoque terapéutico claro.\n- Estado y consultorio actualizados.\n- Disponibilidad real en agenda.\n- Paquetes y sesiones con precios visibles.\n\nConsejo:\nMientras más específico sea tu perfil, mejor coincide con búsquedas reales de pacientes.",
                'estimated_read_minutes' => 4,
                'sort_order' => 40,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Cómo mejorar tu perfil',
                'slug' => 'como-mejorar-tu-perfil',
                'category_key' => 'activar-pacientes',
                'category_name' => 'Activar pacientes',
                'summary' => 'Acciones concretas para que tu perfil inspire más confianza y genere más conversiones.',
                'body' => "Piensa en tu perfil como una primera consulta breve.\n\nAjustes que suelen ayudar:\n- Usa una foto clara y profesional.\n- Explica a quién ayudas y con qué temas trabajas.\n- Evita textos demasiado técnicos o genéricos.\n- Define modalidades: online, presencial o ambas.\n- Mantén horarios y precios actualizados.\n\nSeñal fuerte de confianza:\nUn perfil coherente entre foto, texto, especialidades y experiencia suele convertir mejor.",
                'estimated_read_minutes' => 4,
                'sort_order' => 50,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Cómo conseguir tu primer paciente',
                'slug' => 'como-conseguir-tu-primer-paciente',
                'category_key' => 'activar-pacientes',
                'category_name' => 'Activar pacientes',
                'summary' => 'Estrategias simples para empezar a mover tu perfil y cerrar tus primeras sesiones.',
                'body' => "Tus primeros pacientes suelen llegar cuando combinas visibilidad con claridad.\n\nEmpieza por aquí:\n- Termina tu perfil al 100 por ciento.\n- Publica horarios reales que sí puedas atender.\n- Activa un precio competitivo o un paquete inicial.\n- Comparte tu perfil en WhatsApp, Instagram o con contactos de confianza.\n- Responde leads y solicitudes con rapidez.\n\nMeta realista:\nNo se trata solo de tener visitas; se trata de que quien te vea entienda rápido por qué agendar contigo.",
                'estimated_read_minutes' => 5,
                'sort_order' => 60,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Crear citas',
                'slug' => 'crear-citas',
                'category_key' => 'agenda',
                'category_name' => 'Agenda',
                'summary' => 'Cómo registrar sesiones en tu agenda sin errores de formato, horario o paciente.',
                'body' => "Antes de crear una cita verifica:\n- Paciente correcto.\n- Fecha y hora en tu zona horaria.\n- Formato de sesión.\n- Duración y costo.\n- Si requiere sincronización con Google Calendar.\n\nBuenas prácticas:\n- Evita duplicar sesiones manualmente si serán recurrentes.\n- Revisa disponibilidad antes de confirmar.\n- Usa títulos claros para identificar la sesión rápidamente.",
                'estimated_read_minutes' => 3,
                'sort_order' => 70,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Editar o cancelar citas',
                'slug' => 'editar-o-cancelar-citas',
                'category_key' => 'agenda',
                'category_name' => 'Agenda',
                'summary' => 'Qué revisar al mover, actualizar o cancelar sesiones para mantener tu agenda limpia.',
                'body' => "Cuando edites o canceles una cita cuida estos puntos:\n- Confirmar si es una sola sesión o una serie recurrente.\n- Notificar al paciente con suficiente tiempo.\n- Revisar que Google Calendar refleje el cambio.\n- Verificar que no queden sesiones canceladas visibles por error.\n\nRecomendación:\nSi una recurrencia ya no continuará, cancela las futuras para evitar basura operativa.",
                'estimated_read_minutes' => 4,
                'sort_order' => 80,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Disponibilidad',
                'slug' => 'disponibilidad',
                'category_key' => 'agenda',
                'category_name' => 'Agenda',
                'summary' => 'Cómo configurar tu disponibilidad para que el paciente vea horarios realistas y reservables.',
                'body' => "Tu disponibilidad debe ser honesta y sostenible.\n\nConfigura con cuidado:\n- Horarios recurrentes por día.\n- Bloques no disponibles.\n- Tiempo entre sesiones.\n- Diferencia entre modalidad online y presencial.\n\nTip:\nAgenda vacía no siempre ayuda. Un bloque claro y consistente suele convertir mejor que horarios demasiado dispersos.",
                'estimated_read_minutes' => 4,
                'sort_order' => 90,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Membresía',
                'slug' => 'membresia',
                'category_key' => 'monetizacion',
                'category_name' => 'Monetización',
                'summary' => 'Qué cubre tu membresía, cómo entender su estado y qué hacer si algo no se actualiza.',
                'body' => "Tu membresía impacta visibilidad, acceso y continuidad operativa.\n\nQué revisar siempre:\n- Estado actual de suscripción.\n- Próximo pago o fecha de renovación.\n- Método de pago.\n- Si estás en prueba real, activa o por expirar.\n\nImportante:\nRegistro inicial no es lo mismo que prueba activa. La prueba real comienza cuando Stripe confirma la suscripción correspondiente.",
                'estimated_read_minutes' => 4,
                'sort_order' => 100,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Paquetes de sesiones',
                'slug' => 'paquetes-de-sesiones',
                'category_key' => 'monetizacion',
                'category_name' => 'Monetización',
                'summary' => 'Cómo usar paquetes para aumentar recurrencia y facilitar decisiones de compra.',
                'body' => "Los paquetes funcionan bien cuando comunican ahorro y continuidad.\n\nBuenas prácticas:\n- Muestra el precio por sesión y el ahorro total.\n- Deja claro para qué tipo de paciente aplica.\n- Combina paquetes con promociones o cupones cuando tenga sentido.\n- Revisa que el paquete sí aparezca en tu perfil público.\n\nObjetivo:\nAyudar al paciente a tomar una decisión más fácil cuando quiere continuidad terapéutica.",
                'estimated_read_minutes' => 4,
                'sort_order' => 110,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Estrategias de precios',
                'slug' => 'estrategias-de-precios',
                'category_key' => 'monetizacion',
                'category_name' => 'Monetización',
                'summary' => 'Ideas para fijar precios, descuentos y promociones sin devaluar tu práctica.',
                'body' => "Tu precio debe ser claro para ti y comprensible para el paciente.\n\nPuedes trabajar así:\n- Precio base por sesión individual.\n- Precio preferente por paquete.\n- Promociones con vigencia definida.\n- Cupones para campañas específicas.\n\nEvita:\n- Cambios frecuentes sin explicación.\n- Descuentos permanentes que vuelven confusa tu oferta.\n- Paquetes mal alineados con tus horarios o capacidad real.",
                'estimated_read_minutes' => 5,
                'sort_order' => 120,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Notificaciones',
                'slug' => 'notificaciones',
                'category_key' => 'avanzado',
                'category_name' => 'Avanzado',
                'summary' => 'Qué tipos de notificaciones maneja MindMeet y cómo sacarles provecho operativo.',
                'body' => "MindMeet puede avisarte sobre sesiones, leads, cambios relevantes y recordatorios.\n\nRevisa periódicamente:\n- Campana de notificaciones.\n- Push si ya lo tienes habilitado.\n- Correo para mensajes operativos importantes.\n\nConsejo:\nNo dependas de un solo canal. La combinación de campana, push y correo reduce olvidos.",
                'estimated_read_minutes' => 4,
                'sort_order' => 130,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Configuración',
                'slug' => 'configuracion',
                'category_key' => 'avanzado',
                'category_name' => 'Avanzado',
                'summary' => 'Dónde revisar tu cuenta, servicios, horarios, identidad y datos relevantes.',
                'body' => "La configuración correcta evita muchos problemas operativos.\n\nÁreas importantes:\n- Horarios y disponibilidad.\n- Servicios y formatos de sesión.\n- Datos bancarios y pagos.\n- Identidad y verificación.\n- Información básica y perfil profesional.\n\nRecomendación:\nHaz una revisión rápida cada semana para detectar inconsistencias antes de que afecten leads o agenda.",
                'estimated_read_minutes' => 4,
                'sort_order' => 140,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Herramientas extra',
                'slug' => 'herramientas-extra',
                'category_key' => 'avanzado',
                'category_name' => 'Avanzado',
                'summary' => 'Funciones extra de MindMeet que pueden ahorrarte tiempo y mejorar tu operación.',
                'body' => "Además del catálogo y agenda, MindMeet puede ayudarte con herramientas complementarias.\n\nExplora:\n- Expediente clínico.\n- Contratos y documentos.\n- Cuestionarios.\n- Paquetes y promociones.\n- Analítica de visitas, clics y leads.\n\nPiensa en esto como una caja de herramientas. No necesitas activar todo el mismo día, pero sí conviene conocer qué ya tienes disponible.",
                'estimated_read_minutes' => 4,
                'sort_order' => 150,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('help_center_articles');
    }
};
