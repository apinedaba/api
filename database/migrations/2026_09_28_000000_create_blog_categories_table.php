<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('blog_categories')->nullOnDelete();
        });

        $categories = [
            ['name' => 'Psicología', 'description' => 'Herramientas y conocimiento para comprender, cuidar y fortalecer tu salud mental.'],
            ['name' => 'Neurociencias', 'description' => 'Una mirada clara a cómo funciona el cerebro, las emociones y el comportamiento.'],
            ['name' => 'Vida Saludable', 'description' => 'Hábitos y prácticas para construir un bienestar integral todos los días.'],
            ['name' => 'Entrevistas', 'description' => 'Conversaciones con especialistas y voces que inspiran bienestar emocional.'],
            ['name' => 'Empresas', 'description' => 'Recursos para crear organizaciones más humanas, saludables y conscientes.'],
            ['name' => 'Pareja', 'description' => 'Ideas y herramientas para construir relaciones más sanas y cercanas.'],
        ];

        foreach ($categories as $category) {
            BlogCategory::create([...$category, 'slug' => Str::slug($category['name'])]);
        }

        BlogPost::query()->whereNotNull('category')->each(function (BlogPost $post) {
            $name = trim($post->category);
            if ($name === '') {
                return;
            }

            $category = BlogCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => null],
            );
            $post->update(['category_id' => $category->id]);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('blog_categories');
    }
};
