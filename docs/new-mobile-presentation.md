# Integración en la versión vigente de Patrocinio

Estos módulos aún no están conectados al panel de producción. La versión `main` de este repositorio es anterior al panel observado el 6 de noviembre de 2026. No sustituir `api/upload_letter.php`, `admin/ver_carta.php` ni los generadores PDF vigentes por sus copias antiguas de este repositorio.

## Contrato móvil

La nueva app envía el mismo PNG, mensaje, respuestas y descripciones. Añade `content_version`: vale 2 solo si el mensaje y sus respuestas se guardaron mediante el flujo unificado. Las cartas locales previas conservan 1. Las herramientas, colores y plantillas ya quedan incorporadas en el PNG; no hay que reconstruir ni recolorear el dibujo en PHP.

## Cartas ya enviadas

Conservar contenido, adjuntos, preguntas históricas, estado, formato y PDF. No regenerar ni convertir registros previos. No aplicar CSS de V2 a las plantillas antiguas. Una reintentona de envío no puede convertir una carta histórica a V2.

## Puntos de integración pendientes sobre el código actual

1. Dentro de la transacción autenticada de recepción, obtener y bloquear el registro de la carta ANTES de actualizarlo. Determinar si ya fue recibida por sus datos/fecha de recepción y adjuntos existentes; no basarse solo en el estado, porque una carta recibida puede estar devuelta.
2. Almacenar una versión de presentación inmutable por carta, en una tabla o metadato aditivo. Las cartas sin marca se consideran V1. No actualizar en masa el histórico. Llamar a `ml_presentation_version($versionGuardada, $_POST['content_version'] ?? null, $yaRecibida)` para calcularla; escribir la marca junto con el envío nuevo en la misma transacción. Ante reintentos conservar siempre la marca inicial y la idempotencia actual por submission_id.
3. En revisión, edición y PDF, consultar esa misma marca guardada. Para V1 ejecutar el renderizado actual sin ningún cambio. Para V2 mostrar primero las preguntas/respuestas históricas de la carta y después su mensaje mediante `ml_render_new_letter_content`. No consultar los textos actuales del catálogo de preguntas para reconstruir respuestas anteriores.
4. Incluir `letter_presentation.css` solo en la rama V2. Los dibujos se muestran con `object-fit: contain`, sus colores originales, proporción completa y descripción; reutilizar el manejo seguro de adjuntos actual.
5. En el PDF MCS aplicar la misma selección V1/V2; no regenerar PDFs de cartas ya enviadas. Mantener las protecciones actuales de aprobación, devolución y autorización.

## Comprobación

`php tests/letter_presentation_test.php`

Antes de instalar: obtener los archivos vigentes de recepción, revisión, edición y generación MCS/PDF. Verificar con datos sintéticos el envío V2, un reintento, una carta devuelta y una carta V1 aprobada; la última debe conservar HTML/PDF y adjuntos idénticos. Ningún archivo de datos reales forma parte de esta propuesta.
