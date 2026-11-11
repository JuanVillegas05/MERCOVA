<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Slug único para modelos cuya columna `slug` es UNIQUE en la base de datos
 * (productos, servicios). Dos vendedores pueden publicar el mismo título:
 * el segundo recibe "titulo-2", el tercero "titulo-3", etc., en vez de
 * romper el INSERT con un error de clave duplicada.
 */
trait HasUniqueSlug
{
    public static function uniqueSlugFor(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'publicacion';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
