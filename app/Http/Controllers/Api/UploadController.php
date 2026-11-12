<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinary) {}

    /**
     * Sube una imagen (de un producto, servicio, etc.) y devuelve su URL
     * pública para usarla como image_url al crear/actualizar el recurso.
     *
     * En producción va a Cloudinary (el disco de Render se borra en cada
     * despliegue); sin Cloudinary configurado, al disco "public".
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

        $file = $request->file($fileKey);

        if (! $this->cloudinary->enabled()) {
            $path = $file->store('listings', 'public');

            return response()->json(['url' => Storage::disk('public')->url($path)], 201);
        }

        try {
            $url = $this->cloudinary->uploadImage($file, 'listings');
        } catch (ConnectionException|RequestException $e) {
            report($e);
            abort(503, 'No pudimos subir la imagen en este momento. Intenta de nuevo en unos minutos.');
        }

        return response()->json(['url' => $url], 201);
    }
}
