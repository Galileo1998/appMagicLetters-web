<?php

/** Read tickets in their page regions, never by zipping independent regex lists. */
final class PdfTicketReader
{
    private const TYPE = '(?:Child\s+Welcome\s+Letter|Child\s+Reply\s+Letter|Thank\s+You\s+Letter)';
    private const LABEL = '(?:Slip\s*Id|Community\s*Id|Date\s*Request|Due\s*Date|Case|Child\s*Nbr|Sex|Birthdate|Village|Child\s*Name|Contact\s*Id|Contact\s*Name|IA\s*ID|Addl\s*Msg)\s*:';

    public static function read(\Smalot\PdfParser\Document $pdf): array
    {
        $tickets = [];
        foreach ($pdf->getPages() as $page) {
            $items = [];
            foreach (self::positionedText($page) as $entry) {
                $text = trim(preg_replace('/\s+/u', ' ', $entry[1]));
                if ($text !== '') {
                    $items[] = ['x' => (float) $entry[0][4], 'y' => (float) $entry[0][5], 'text' => $text, 'order' => count($items)];
                }
            }
            foreach (self::readPage($items) as $ticket) {
                $tickets[] = $ticket;
            }
        }
        if (!$tickets) {
            throw new \RuntimeException('No se encontraron tickets legibles. Revise el documento original; no se importaron datos.');
        }
        return $tickets;
    }

    private static function multiply(array $a, array $b): array
    {
        return [
            $a[0]*$b[0]+$a[1]*$b[2], $a[0]*$b[1]+$a[1]*$b[3],
            $a[2]*$b[0]+$a[3]*$b[2], $a[2]*$b[1]+$a[3]*$b[3],
            $a[4]*$b[0]+$a[5]*$b[2]+$b[4], $a[4]*$b[1]+$a[5]*$b[3]+$b[5],
        ];
    }

    private static function positionedText(\Smalot\PdfParser\Page $page): array
    {
        // PdfParser 2.12 composes nested cm operators in reverse order. Use its
        // decoded strings, but compose graphics matrices in PDF drawing order.
        $identity = [1, 0, 0, 1, 0, 0];
        $ctm = $line = $identity;
        $stack = [];
        $leading = 0;
        $texts = $page->getTextArray();
        $index = 0;
        $out = [];
        foreach ($page->getDataCommands() as $command) {
            $op = $command['o'];
            if ($op === 'q') {
                $stack[] = [$ctm, $leading];
            } elseif ($op === 'Q') {
                if ($stack) { [$ctm, $leading] = array_pop($stack); }
            } elseif ($op === 'cm') {
                $matrix = array_map('floatval', preg_split('/\s+/', trim($command['c'])));
                $ctm = self::multiply($matrix, $ctm);
            } elseif ($op === 'BT') {
                $line = $identity;
            } elseif ($op === 'Tm') {
                $line = array_map('floatval', preg_split('/\s+/', trim($command['c'])));
            } elseif ($op === 'TL') {
                $leading = (float) $command['c'];
            } elseif ($op === 'Td' || $op === 'TD') {
                $delta = array_map('floatval', preg_split('/\s+/', trim($command['c'])));
                if ($op === 'TD') { $leading = -$delta[1]; }
                $line = self::multiply([1, 0, 0, 1, $delta[0], $delta[1]], $line);
            }
            if (in_array($op, ['T*', "'", '"'], true)) {
                $line = self::multiply([1, 0, 0, 1, 0, -$leading], $line);
            }
            if (in_array($op, ['Tj', 'TJ', "'", '"'], true)) {
                if (!array_key_exists($index, $texts)) {
                    throw new \RuntimeException('No se pudo reconstruir la posición del texto del PDF.');
                }
                $out[] = [self::multiply($line, $ctm), $texts[$index++]];
            }
        }
        if ($index !== count($texts)) {
            throw new \RuntimeException('No se pudo reconstruir todo el texto del PDF.');
        }
        return $out;
    }

    public static function readPage(array $items): array
    {
        // Slip-label positions identify the left edge of each column, not its title's center.
        $columns = [];
        foreach ($items as $item) {
            if (preg_match('/^Slip(?:\s*Id\s*:)?$/i', $item['text']) || preg_match('/Slip\s*Id\s*:/i', $item['text'])) {
                $found = false;
                foreach ($columns as $x) {
                    if (abs($x - $item['x']) < 12) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $columns[] = $item['x'];
                }
            }
        }
        sort($columns);
        $result = [];
        foreach ($columns as $index => $left) {
            $right = isset($columns[$index + 1]) ? $columns[$index + 1] - 12 : INF;
            $column = array_values(array_filter($items, static function ($item) use ($left, $right) {
                return $item['x'] >= $left - 12 && $item['x'] < $right;
            }));
            $columnLines = self::lines($column);
            $headers = array_values(array_filter($columnLines, static function ($item) {
                return preg_match('/' . self::TYPE . '/i', $item['text']);
            }));
            usort($headers, static function ($a, $b) { return $b['y'] <=> $a['y']; });
            $slipCount = count(array_filter($columnLines, static function ($item) {
                return preg_match('/Slip\s*Id\s*:/i', $item['text']);
            }));
            if (count($headers) !== $slipCount) {
                throw new \RuntimeException('No se pudo separar cada ticket con seguridad. Revise el formato del PDF; no se importaron datos.');
            }
            foreach ($headers as $i => $header) {
                // Header labels may differ by a point in baseline (Date Request vs title).
                $top = $header['y'] + 3;
                $bottom = isset($headers[$i + 1]) ? $headers[$i + 1]['y'] + 3 : -INF;
                $region = array_values(array_filter($column, static function ($item) use ($top, $bottom) {
                    return $item['y'] <= $top && $item['y'] > $bottom;
                }));
                $ticket = self::parseTicket(implode("\n", array_column(self::lines($region), 'text')));
                if ($ticket['slip_id'] === '') {
                    throw new \RuntimeException('Un ticket no tiene Slip ID legible. Revise el documento original.');
                }
                $result[] = $ticket;
            }
        }
        return $result;
    }

    private static function lines(array $items): array
    {
        usort($items, static function ($a, $b) { return ($b['y'] <=> $a['y']) ?: (($a['order'] ?? 0) <=> ($b['order'] ?? 0)); });
        $lines = [];
        foreach ($items as $item) {
            $last = count($lines) - 1;
            if ($last < 0 || abs($lines[$last]['y'] - $item['y']) > 2) {
                $lines[] = ['y' => $item['y'], 'items' => [$item]];
            } else {
                $lines[$last]['items'][] = $item;
            }
        }
        $text = [];
        foreach ($lines as $line) {
            usort($line['items'], static function ($a, $b) { return ($a['x'] <=> $b['x']) ?: (($a['order'] ?? 0) <=> ($b['order'] ?? 0)); });
            $text[] = ['x' => $line['items'][0]['x'], 'y' => $line['y'], 'text' => implode(' ', array_column($line['items'], 'text'))];
        }
        return $text;
    }

    public static function parseTicket(string $text): array
    {
        // Barcode and footer are not field content.
        $text = preg_replace('/\*\d+\*|Page\s+\d+\s+Of\s+\d+/i', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $fields = [
            'slip_id' => 'Slip\s*Id', 'community_id' => 'Community\s*Id',
            'request_date' => 'Date\s*Request', 'due_date' => 'Due\s*Date',
            'child_nbr' => 'Child\s*Nbr', 'sex' => 'Sex', 'birthdate' => 'Birthdate',
            'village' => 'Village', 'child_name' => 'Child\s*Name',
            'contact_id' => 'Contact\s*Id', 'contact_name' => 'Contact\s*Name', 'ia_id' => 'IA\s*ID',
        ];
        $row = [];
        foreach ($fields as $key => $label) {
            preg_match('/' . $label . '\s*:\s*(.*?)(?=' . self::LABEL . '|$)/i', $text, $match);
            $value = trim($match[1] ?? '');
            if (in_array($key, ['slip_id', 'community_id', 'child_nbr', 'contact_id'], true)) {
                $value = preg_match('/^(\d+)\b/', $value, $number) ? $number[1] : '';
            } elseif (in_array($key, ['request_date', 'due_date', 'birthdate'], true)) {
                $value = preg_match('/^(\d{1,2}-[A-Za-z]{3}-\d{4})\b/', $value, $date) ? $date[1] : '';
            } elseif ($key === 'sex') {
                $value = preg_match('/^[MF]$/i', $value) ? strtoupper($value) : '';
            }
            $row[$key] = $value;
        }
        preg_match('/' . self::TYPE . '/i', $text, $type);
        $row['letter_type'] = preg_replace('/\s+/', ' ', $type[0] ?? 'Unknown');
        return $row;
    }
}
