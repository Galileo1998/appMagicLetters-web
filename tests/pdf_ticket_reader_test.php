<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../admin/pdf_ticket_reader.php';

function same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . json_encode(['expected' => $expected, 'actual' => $actual]));
    }
}

$parser = new Smalot\PdfParser\Parser();
$rows = PdfTicketReader::read($parser->parseFile(__DIR__ . '/fixtures/tickets.pdf'));
same(7, count($rows), 'Both columns and all pages are read');
$types = ['Child Reply Letter', 'Child Welcome Letter', 'Thank You Letter'];
foreach ($rows as $i => $row) {
    $n = $i + 1;
    same([
        'slip_id' => sprintf('%010d', $n), 'community_id' => '4089',
        'request_date' => '18-Oct-2026', 'due_date' => '02-Dec-2026',
        'child_nbr' => (string) (900000000 + $n), 'sex' => 'F', 'birthdate' => '01-Jan-2018',
        'village' => 'Comunidad Centro', 'child_name' => $n === 2 ? '' : 'Persona de prueba ' . $n,
        'contact_id' => (string) (7000000 + $n), 'contact_name' => "Mr. O'Brien " . $n,
        'ia_id' => 'ChildFund International - USA', 'letter_type' => $types[$i % 3],
    ], $row, 'Fields remain attached to ticket ' . $n);
}

// Header and body need to be present in the same region. Never silently drop a ticket.
try {
    PdfTicketReader::readPage([['x' => 10, 'y' => 700, 'text' => 'Slip Id: 0000000012']]);
    throw new RuntimeException('An unrecognized layout was accepted');
} catch (RuntimeException $e) {
    same(true, strpos($e->getMessage(), 'separar') !== false, 'Unsafe layout is rejected');
}
same('', PdfTicketReader::parseTicket('Community Id: 4089Child Reply Letter')['community_id'], 'Malformed numeric fields are not accepted');
echo "PASS: 7 synthetic tickets, 13 fields each, nested transforms, two columns, multiple pages, missing field, three types and unsafe layout.\n";
