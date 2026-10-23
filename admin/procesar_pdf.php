<?php
session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

if (isset($_FILES['pdf_file']['error']) && $_FILES['pdf_file']['error'] !== 0) {
    die("Error de PHP al subir: " . $_FILES['pdf_file']['error']);
}
require '../vendor/autoload.php';

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    die("Error: No se seleccionó ningún archivo o hubo un problema en la subida.");
}

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile($_FILES['pdf_file']['tmp_name']);
require_once __DIR__ . '/pdf_ticket_reader.php';
try {
    $data_extracted = PdfTicketReader::read($pdf);
    foreach ($data_extracted as &$ticket) {
        $daysToAdd = 7;
        if (stripos($ticket['letter_type'], 'Welcome') !== false) $daysToAdd = 5;
        elseif (stripos($ticket['letter_type'], 'Reply') !== false) $daysToAdd = 14;
        elseif (stripos($ticket['letter_type'], 'Thank') !== false) $daysToAdd = 20;
        $ticket['tech_date'] = (new DateTime('now', new DateTimeZone('America/Tegucigalpa')))
            ->modify("+$daysToAdd days")->format('d-M-Y');
    }
    unset($ticket);
} catch (\Throwable $e) {
    http_response_code(422);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$_SESSION['temp_data'] = $data_extracted;
header("Location: revisar_carga.php"); // Redirige a revisar antes de guardar
exit;
?>