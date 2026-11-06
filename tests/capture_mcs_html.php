<?php
namespace Dompdf {
    class Options { public function set($key, $value) {} }
    class Dompdf {
        private $html;
        public function __construct($options) {}
        public function loadHtml($html, $encoding) { $this->html = $html; }
        public function setPaper($paper, $orientation) {}
        public function render() {}
        public function output() { return $this->html; }
    }
}
namespace {
    require $argv[1];
    $document = ['letter'=>['id'=>99999,'child_nbr'=>'900000001','child_code'=>'900000001','child_name'=>'Persona de prueba','letter_type'=>'Child Reply Letter','slip_id'=>'0000000001','village'=>'Municipio de prueba','final_message'=>str_repeat("Texto completo de prueba sin recortes.\n\n", 80) . 'ULTIMA LINEA DE PRUEBA'], 'attachments'=>[], 'answers'=>[['question_text'=>'¿Cuál es tu juego favorito?','answer_text'=>'Dibujar y jugar.']]];
    if (isset($argv[2])) $document['presentation_version'] = (int)$argv[2];
    echo mcs_render_letter_pdf($document);
}
