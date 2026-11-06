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
$answers = [['question_text'=>'¿Qué dibujaste?', 'answer_text'=>"Un árbol.\nCon regalos."], ['question_text'=>'', 'answer_text'=>'']];
$html = ml_render_new_letter_content(2, "Mi mensaje\n<script>alert(1)</script>", $answers);
check(true, strpos($html, 'Pregunta seleccionada') < strpos($html, 'Texto completo'), 'Question must precede message');
check(true, strpos($html, 'CONTENIDO') < strpos($html, 'Pregunta seleccionada'), 'Content heading precedes the selected question');
check(false, strpos($html, '<script>') !== false, 'Escape incoming text');
check(true, strpos($html, '&lt;script&gt;') !== false, 'Keep escaped content visible');
check(false, strpos($html, '<dt></dt>') !== false, 'Do not fabricate unanswered content');
check(true, strpos($html, "Un árbol.<br />\nCon regalos.") !== false, 'Preserve paragraphs');
echo "PASS: immutable historical formats, explicit V2 opt-in, question-before-message, escaping and paragraphs.\n";

$selected = ['question_id'=>3,'question_text'=>'¿Cuál es tu juego favorito?','answer_text'=>'','answer_in_message'=>1];
check(true, ml_should_store_question(2, $selected), 'V2 selection is stored without a separate response');
check(false, ml_should_store_question(1, $selected), 'Legacy submissions cannot create blank responses');
$withoutFlag = $selected; unset($withoutFlag['answer_in_message']);
check(false, ml_should_store_question(2, $withoutFlag), 'Older apps blank unselected questions are ignored');
$selectedHtml = ml_render_new_letter_content(2, 'RESPUESTA EN EL MENSAJE COMPLETO', [$selected]);
check(true, strpos($selectedHtml, $selected['question_text']) < strpos($selectedHtml, 'Texto completo'), 'Prompt is shown before full composition');
check(1, substr_count($selectedHtml, 'RESPUESTA EN EL MENSAJE COMPLETO'), 'Message is never duplicated as a separate answer');
check(false, strpos($selectedHtml, '<dd>') !== false, 'No empty separate response is shown');
echo "PASS: selected question with full-message answer; legacy and older-client compatibility.\n";
