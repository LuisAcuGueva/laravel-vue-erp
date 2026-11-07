## Archivos adjuntos y comprobantes

- Los archivos con información sensible (comprobantes bancarios, documentos de proveedores y clientes) se guardan en el disco `local` (privado) y se descargan solo por un endpoint autenticado con permiso que usa `Storage::download()`; las respuestas JSON exponen la URL de ese endpoint, nunca la ruta física.
- Solo los archivos de marca (logo de empresa) van al disco `public`.
- Toda subida valida `mimes` y `max` (KB) en el FormRequest.
