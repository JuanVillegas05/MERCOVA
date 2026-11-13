# 0003. Guardar las imágenes en Cloudinary

**Estado:** vigente

## Contexto

Las imágenes que subían los vendedores se guardaban en `storage/app/public` del servidor. En Render (plan gratuito) ese disco se borra en cada despliegue o reinicio, así que las publicaciones quedaban con la imagen rota. Además, en local la URL generada (`http://localhost:8000/storage/...`) no se puede abrir desde otro dispositivo.

## Decisión

`POST /api/uploads/image` sube la imagen a Cloudinary con una petición firmada (`CloudinaryService`) y devuelve una URL `https` pública. Cloudinary la limita a 1600 px y ajusta la calidad al subirla. La credencial se configura con `CLOUDINARY_URL`.

En producción, si falta `CLOUDINARY_URL`, la subida responde `503` en vez de guardar en el disco efímero. En local, sin Cloudinary, se sigue usando el disco `public`.

## Alternativas descartadas

- **Disco persistente de Render:** solo existe en planes de pago y no permite escalar a varias instancias.
- **Amazon S3:** requiere cuenta de AWS, un paquete adicional y configurar permisos públicos del bucket.

## Consecuencias y límites

- Las imágenes se ven desde cualquier dispositivo y no dependen del servidor.
- Las imágenes subidas antes de este cambio se perdieron y hay que volver a subirlas.
- Sin `CLOUDINARY_URL` en producción no se pueden publicar imágenes nuevas.
