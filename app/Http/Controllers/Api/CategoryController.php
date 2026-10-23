<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Lista plana (padres e hijas) con el id del padre; el frontend construye el árbol.
     * Sin envoltorio "data": varias páginas ya consumen este endpoint como arreglo plano.
     */
    public function index()
    {
        return Category::query()
            ->with('parent')
            ->withCount(['products', 'services'])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->parent_id !== null) {
                abort(422, 'Una subcategoría no puede tener a su vez otra subcategoría como padre.');
            }
        }

        $validated['slug'] = $this->uniqueSlug($validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Categoría creada con éxito.',
            'data' => $category->load('parent'),
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'parent_id' => ['sometimes', 'nullable', 'exists:categories,id'],
        ]);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            if ((int) $validated['parent_id'] === $category->id) {
                abort(422, 'Una categoría no puede ser su propia subcategoría.');
            }

            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->parent_id !== null) {
                abort(422, 'Una subcategoría no puede tener a su vez otra subcategoría como padre.');
            }

            if ($category->children()->exists()) {
                abort(422, 'Esta categoría tiene subcategorías propias: no puede convertirse en subcategoría de otra.');
            }
        }

        if (isset($validated['name'])) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $category->id);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => $category->fresh()->load('parent'),
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        if ($category->products()->exists() || $category->services()->exists()) {
            abort(422, 'No puedes eliminar una categoría que tiene productos o servicios publicados.');
        }

        if ($category->children()->exists()) {
            abort(422, 'No puedes eliminar una categoría que tiene subcategorías. Elimínalas primero.');
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
