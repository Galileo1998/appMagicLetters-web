# Presentación de cartas del nuevo flujo móvil

Instalado en `public_html/patrocinio` el 6 de noviembre de 2026, sobre los archivos vigentes obtenidos de cPanel. El main de este repositorio es anterior a producción: no sustituir archivos completos del servidor por las copias antiguas del repositorio.

## Archivos e instalación

`deployments/20261106-production.patch` contiene únicamente las modificaciones de los cuatro archivos actuales de producción: recepción API, revisión, edición y generación MCS/PDF. Los módulos nuevos están en `lib/letter_presentation.php` y `.css`; la tabla aditiva está en `db/20261106_letter_presentation.sql`. La instalación ya se realizó. No volver a aplicar el parche sin comprobar su estado. No se descargaron configuraciones ni credenciales.

Se guardaron copias originales fuera de public_html, en el directorio privado `patrocinio-cambio-20261106/original`. El registro `deployed.json` del mismo directorio contiene los hashes antes/después y de los archivos instalados. Para revertir el renderizado se pueden restaurar esos cuatro originales; conservar la tabla de versiones para futuras reinstalaciones.

## Contrato y conservación del histórico

La app nueva envía PNG, mensaje, respuestas y descripciones como antes, añadiendo `content_version=2` únicamente tras guardar el flujo unificado. Cartas locales previas conservan 1. La nueva app aún requiere compilar e instalar su APK.

La API conserva autenticación, validación, auditoría e idempotencia existentes. Bloquea la carta en la transacción, rechaza las aprobadas/SYNCED, y resuelve la versión antes de cambiar datos. La versión inicial es inmutable: una carta con recepción, mensaje o adjuntos previos permanece V1 incluso si un reintento pide V2. Para una primera recepción V2 se guardan la marca y las respuestas seleccionadas en la misma transacción.

La tabla `letter_presentation_versions` comenzó vacía: no hubo conversión ni actualización masiva. Las cartas sin marca usan exactamente el renderizador y estilos anteriores. No se regeneraron sus PDFs. La marca V2 muestra CONTENIDO, pregunta seleccionada con su respuesta y luego texto completo, en revisión, edición y PDF. Sus estilos están limitados a V2 y no recortan mensaje ni descripción del dibujo.

## Verificación realizada

- Sintaxis PHP de todos los módulos modificados correcta.
- `php tests/letter_presentation_test.php`: conservación de versión, orden, texto completo y escape HTML.
- Captura de HTML con `tests/capture_mcs_html.php`: la salida PDF/HTML V1 del módulo original y del modificado resultó idéntica byte por byte. Ejecutar pasando la ruta del módulo MCS y opcionalmente la versión 1 o 2; requiere las respectivas copias del módulo.
- Dompdf real del servidor generó un PDF V2 con datos ficticios, 25 repeticiones de dos párrafos y marcador final: orden correcto y texto íntegro, revisado visualmente y con extracción de texto.
- Hash antes/después idéntico para las 20 cartas históricas recibidas y sus respuestas, recibos y adjuntos, incluidos los bytes de archivos. Ninguna carta real se utilizó para crear envíos de prueba.
- Una carta histórica aprobada mostró la misma pantalla tras recargar.
- El endpoint temporal autenticado de demostración se retiró de public_html al terminar.

Las pruebas no incluyeron un envío real desde la APK actualizada ni mediciones de tacto en dispositivos físicos; quedan para validar la app móvil al distribuirla.
