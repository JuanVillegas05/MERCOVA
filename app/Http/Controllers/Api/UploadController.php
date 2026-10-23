<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Sube una imagen (de un producto, servicio, etc.) y devuelve su URL
     * pública para usarla como image_url al crear/actualizar el recurso.
     */
    public function store(Request $request)
    {
        // El frontend aparte no siempre manda el archivo bajo la misma clave;
        // aceptamos las variantes más comunes en vez de exigir una sola.
        $fileKey = collect(['image', 'file', 'photo'])->first(fn ($key) => $request->hasFile($key));

        if (! $fileKey) {
            abort(422, 'No se recibió ningún archivo de imagen.');
        }

        $request->validate([
            $fileKey => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file($fileKey)->store('listings', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }
}
