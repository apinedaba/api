<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminBlogCategoryController extends Controller
{
    public function store(Request $request)
    {
        BlogCategory::create($this->payload($request));

        return Redirect::route('blog-posts.index')->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, BlogCategory $blogCategory)
    {
        $blogCategory->update($this->payload($request, $blogCategory));

        return Redirect::route('blog-posts.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(BlogCategory $blogCategory)
    {
        if ($blogCategory->posts()->exists()) {
            return Redirect::route('blog-posts.index')->with('error', 'No puedes eliminar una categoría que tiene artículos asignados.');
        }

        $blogCategory->delete();

        return Redirect::route('blog-posts.index')->with('success', 'Categoría eliminada correctamente.');
    }

    private function payload(Request $request, ?BlogCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('blog_categories', 'name')->ignore($category)],
            'description' => ['nullable', 'string', 'max:600'],
        ]);

        $slug = Str::slug($data['name']);
        $slugIsTaken = BlogCategory::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($slugIsTaken) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con este nombre o URL.']);
        }

        return [...$data, 'slug' => $slug];
    }
}
