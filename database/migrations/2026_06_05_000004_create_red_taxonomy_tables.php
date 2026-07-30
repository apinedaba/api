<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 100)->unique();
            $table->string('description', 240)->nullable();
            $table->string('color', 20)->default('#0284c7');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('red_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('slug', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('user_id')
                ->constrained('red_categories')
                ->nullOnDelete();
            $table->index(['category_id', 'is_active', 'created_at']);
        });

        $categories = [
            ['name' => 'Casos clínicos', 'description' => 'Dudas de intervención, evolución y formulación de casos.', 'color' => '#0284c7'],
            ['name' => 'Enfoques terapéuticos', 'description' => 'Técnicas, modelos y herramientas de intervención.', 'color' => '#7c3aed'],
            ['name' => 'Poblaciones', 'description' => 'Trabajo con grupos etarios, parejas y familias.', 'color' => '#059669'],
            ['name' => 'Diagnóstico y evaluación', 'description' => 'Evaluación clínica, diagnóstico diferencial e instrumentos.', 'color' => '#d97706'],
            ['name' => 'Práctica profesional', 'description' => 'Supervisión, ética y desarrollo profesional.', 'color' => '#475569'],
        ];

        foreach ($categories as $index => $category) {
            DB::table('red_categories')->insert([
                ...$category,
                'slug' => Str::slug($category['name']),
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tags = [
            'Ansiedad', 'Depresión', 'Trauma', 'Duelo', 'Adicciones',
            'TCC', 'Psicoanálisis', 'Sistémica', 'Humanista', 'Mindfulness',
            'Infancia', 'Adolescentes', 'Adultos', 'Pareja', 'Familia',
            'Diagnóstico', 'TDAH', 'Autismo', 'Evaluación', 'Farmacología',
            'Supervisión', 'Ética', 'Consulta privada', 'Teleterapia',
        ];

        foreach ($tags as $index => $tag) {
            DB::table('red_tags')->insert([
                'name' => $tag,
                'slug' => Str::slug($tag),
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingTagNames = DB::table('red_preguntas')
            ->pluck('tags')
            ->flatMap(fn ($value) => json_decode($value ?: '[]', true) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique();

        foreach ($existingTagNames as $tag) {
            if (DB::table('red_tags')->where('name', $tag)->exists()) {
                continue;
            }

            $baseSlug = Str::slug($tag) ?: 'etiqueta';
            $slug = $baseSlug;
            $suffix = 2;
            while (DB::table('red_tags')->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            DB::table('red_tags')->insert([
                'name' => $tag,
                'slug' => $slug,
                'sort_order' => 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $categoryIds = DB::table('red_categories')->pluck('id', 'name');
        $categoryByTag = [
            'TCC' => 'Enfoques terapéuticos',
            'Psicoanálisis' => 'Enfoques terapéuticos',
            'Sistémica' => 'Enfoques terapéuticos',
            'Humanista' => 'Enfoques terapéuticos',
            'Mindfulness' => 'Enfoques terapéuticos',
            'Infancia' => 'Poblaciones',
            'Adolescentes' => 'Poblaciones',
            'Adultos' => 'Poblaciones',
            'Pareja' => 'Poblaciones',
            'Familia' => 'Poblaciones',
            'Diagnóstico' => 'Diagnóstico y evaluación',
            'TDAH' => 'Diagnóstico y evaluación',
            'Autismo' => 'Diagnóstico y evaluación',
            'Evaluación' => 'Diagnóstico y evaluación',
            'Farmacología' => 'Diagnóstico y evaluación',
            'Supervisión' => 'Práctica profesional',
            'Ética' => 'Práctica profesional',
            'Consulta privada' => 'Práctica profesional',
            'Teleterapia' => 'Práctica profesional',
        ];

        DB::table('red_preguntas')->select(['id', 'tags'])->orderBy('id')->each(function ($question) use ($categoryByTag, $categoryIds) {
            $tags = json_decode($question->tags ?: '[]', true) ?: [];
            $categoryName = collect($tags)->map(fn ($tag) => $categoryByTag[$tag] ?? null)->filter()->first()
                ?? 'Casos clínicos';

            DB::table('red_preguntas')->where('id', $question->id)->update([
                'category_id' => $categoryIds[$categoryName],
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('red_tags');
        Schema::dropIfExists('red_categories');
    }
};
