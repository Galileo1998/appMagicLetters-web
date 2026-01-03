<?php
session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión de admin, mandar al login principal
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

// LINEA PARA DEPURAR:
if (isset($_FILES['pdf_file']['error']) && $_FILES['pdf_file']['error'] !== 0) {
    die("Error de PHP al subir: " . $_FILES['pdf_file']['error'] . 
        " (1=Excede tamaño, 4=No se subió nada)");
}
require '../vendor/autoload.php';

// Validamos que el archivo se haya subido sin errores
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    die("Error: No se seleccionó ningún archivo o hubo un problema en la subida.");
}

$parser = new \Smalot\PdfParser\Parser();
// Usamos tmp_name que es la ubicación real del archivo en el servidor
$pdf = $parser->parseFile($_FILES['pdf_file']['tmp_name']);
$text = $pdf->getText();

// Dividimos el texto por cada carta (cada bloque empieza con "Child Welcome Letter")
$blocks = explode("Child Welcome Letter", $text);
$data_extracted = [];

foreach ($blocks as $block) {
    if (trim($block) === "") continue;

    // Extraer campos específicos usando los patrones de tu documento
    preg_match('/Slip Id:\s*(\d+)/', $block, $slip);
    preg_match('/Community Id:\s*(\d+)/', $block, $comm);
    preg_match('/Child Nbr:\s*(\d+)/', $block, $childNbr);
    preg_match('/Child Name:\s*(.*?)\s*Contact Id/s', $block, $name);
    preg_match('/Village:\s*(.*)/', $block, $village);
    preg_match('/Due Date:\s*([\d\-\w]+)/', $block, $dueDate);

    if (isset($slip[1])) {
        $data_extracted[] = [
            'slip_id' => $slip[1],
            'community_id' => $comm[1] ?? '',
            'child_nbr' => $childNbr[1] ?? '',
            'child_name' => trim($name[1] ?? 'Sin nombre'),
            'village' => trim($village[1] ?? ''),
            'due_date' => $dueDate[1] ?? ''
        ];
    }
}
// Redirigir a la vista de edición con los datos
$_SESSION['temp_data'] = $data_extracted;