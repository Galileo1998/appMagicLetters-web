<?php
require __DIR__ . '/../lib/letter_presentation.php';
function check($expected, $actual, string $why): void {
    if ($expected !== $actual) throw new RuntimeException($why);
}
// A previously received letter must never switch formats on retry or return.
check(1, ml_presentation_version(null, 2, true), 'Historical receipt must remain legacy');
check(1, ml_presentation_version(1, 2, false), 'Stored legacy format is immutable');
check(2, ml_presentation_version(2, 1, true), 'Stored new format survives an old-client retry');
check(2, ml_presentation_version(null, '2', false), 'Only a new receipt can opt in');
check(1, ml_presentation_version(null, null, false), 'Old app remains compatible');
check(1, ml_presentation_version(null, '22', false), 'Unknown version fails closed');
check('', ml_render_new_letter_content(1, 'Mensaje histórico', []), 'Historical HTML remains with the legacy renderer');
$answers = [['question_text'=>'¿Qué dibujaste?', 'answer_text'=>"Un árbol.\nCon regalos."], ['question_text'=>'No respondida', 'answer_text'=>'']];
$html = ml_render_new_letter_content(2, "Mi mensaje\n<script>alert(1)</script>", $answers);
check(true, strpos($html, 'Pregunta seleccionada') < strpos($html, 'Texto completo'), 'Question must precede message');
check(true, strpos($html, 'CONTENIDO') < strpos($html, 'Pregunta seleccionada'), 'Content heading precedes the selected question');
check(false, strpos($html, '<script>') !== false, 'Escape incoming text');
check(true, strpos($html, '&lt;script&gt;') !== false, 'Keep escaped content visible');
check(false, strpos($html, 'No respondida') !== false, 'Do not fabricate unanswered content');
check(true, strpos($html, "Un árbol.<br />\nCon regalos.") !== false, 'Preserve paragraphs');
echo "PASS: immutable historical formats, explicit V2 opt-in, question-before-message, escaping and paragraphs.\n";
