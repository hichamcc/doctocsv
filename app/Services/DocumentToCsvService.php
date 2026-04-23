<?php

namespace App\Services;

class DocumentToCsvService
{
    public function convert(array $records): string
    {
        if (empty($records)) {
            return '';
        }

        $headers = array_keys($records[0]);
        $output  = fopen('php://temp', 'r+');

        fputcsv($output, $headers);

        foreach ($records as $record) {
            $row = [];
            foreach ($headers as $header) {
                $value = $record[$header] ?? null;
                $row[] = is_null($value) ? '' : (is_array($value) ? json_encode($value) : (string) $value);
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
