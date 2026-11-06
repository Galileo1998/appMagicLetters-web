<?php
/**
 * Explicit opt-in for the new mobile workflow. No database or filesystem writes.
 * Existing received letters always retain their saved presentation version.
 */
function ml_presentation_version($savedVersion, $incomingVersion, bool $alreadyReceived): int
{
    if ($savedVersion !== null) {
        return (string) $savedVersion === '2' ? 2 : 1;
    }
    if ($alreadyReceived) {
        return 1;
    }
    return (string) $incomingVersion === '2' ? 2 : 1;
}

function ml_presentation_text($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Render only V2. Returning an empty string leaves the legacy renderer untouched. */
function ml_render_new_letter_content(int $savedVersion, string $message, array $answers): string
{
    if ($savedVersion !== 2) {
        return '';
    }
    $html = '<section class="ml-content-v2" aria-label="Preguntas y mensaje">';
    $html .= '<h3>Preguntas y mensaje</h3>';
    $answered = [];
    foreach ($answers as $answer) {
        if (!is_array($answer) || trim((string) ($answer['answer_text'] ?? '')) === '') {
            continue;
        }
        $answered[] = $answer;
    }
    if ($answered) {
        $html .= '<section class="ml-questions-v2" aria-label="Preguntas consultadas"><h4>Preguntas consultadas</h4><dl>';
        foreach ($answered as $answer) {
            $html .= '<div class="ml-answer-v2"><dt>' . ml_presentation_text($answer['question_text'] ?? '') . '</dt>';
            $html .= '<dd>' . nl2br(ml_presentation_text($answer['answer_text'])) . '</dd></div>';
        }
        $html .= '</dl></section>';
    }
    $html .= '<section aria-label="Contenido de la carta"><h4>Contenido de la carta</h4>';
    $html .= '<div class="ml-message-v2">' . nl2br(ml_presentation_text($message)) . '</div></section></section>';
    return $html;
}
