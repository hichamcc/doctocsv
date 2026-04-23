<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetFactory;
use PhpOffice\PhpWord\IOFactory as WordFactory;

class AiDocumentExtractor
{
    private const GENERATE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent';

    private const PROMPT = <<<'PROMPT'
Extract all structured data from this document and return it as a JSON array
of objects suitable for CSV export. Follow these rules strictly:

DOCUMENT STRUCTURE AWARENESS:
- Documents may contain: (a) a metadata/header table with document-level info
  (dates, company names, references), (b) one or more ORDER/DATA tables with
  line items, and (c) free-text footers with instructions or signatures.
- The ORDER/DATA table rows are ALWAYS the primary rows.
- Metadata fields (document date, sender company, sender contact, recipient
  company) must be merged into every order row as extra columns.
- Free-text instructions and signatures must also be merged in as extra columns
  (e.g. "requirements", "signature_name"), NOT treated as standalone rows.
- Never make a free-text block or signature the primary row.

EXTRACTION RULES:
1. Each object = one row. All objects in the array must have the exact same keys.
2. Key naming: snake_case, English, lowercase, no special characters.
   Translate non-English headers to English (e.g. "Lastn.dag" → "loading_date",
   "Mængde" → "quantity", "Kund" → "customer").
3. Identify the ORDER/DATA table (the one with line items like dates, products,
   quantities, order numbers, customers). Each row in that table = one output
   object. Add all other document fields (from header tables and free text) as
   extra keys on every row.
4. If the document contains contextual data OUTSIDE the table (e.g. a header
   block, sender info, reference numbers, dates, instructions), add those fields
   to EVERY row object as repeated values — do not discard them.
5. If the document has multiple distinct tables or record types, merge their
   shared context fields and return the most granular rows (order lines,
   shipment legs, etc.).
6. Flatten all nested or multi-line values: use separate keys
   (e.g. "loading_address_street", "loading_address_city", "loading_address_country").
7. Values: trim whitespace. Keep original formatting for dates and numbers —
   do not convert or reformat.
8. Missing or empty values: use null (not empty string).
9. Never nest objects or arrays as values.
10. If there is only one record, still return a single-element array.

Return ONLY the raw JSON array. No explanation, no markdown, no code fences.
PROMPT;

    public function extract(string $filePath, ?string $instructions = null): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $apiKey    = config('services.gemini.api_key');

        Log::debug('[AiDocumentExtractor] starting extraction', [
            'file'      => basename($filePath),
            'extension' => $extension,
            'api_key'   => $apiKey ? ('set, ends in …' . substr($apiKey, -4)) : 'MISSING',
        ]);

        $prompt = self::PROMPT . ($instructions ? "\n\nADDITIONAL INSTRUCTIONS:\n{$instructions}" : '');

        if ($extension === 'pdf') {
            return $this->generateFromInline($filePath, 'application/pdf', $apiKey, $prompt);
        }

        if ($extension === 'docx') {
            $html = $this->docxToHtml($filePath);
            return $this->generateFromText($html, $apiKey, $prompt);
        }

        if ($extension === 'xlsx') {
            $csv = $this->xlsxToCsv($filePath);
            return $this->generateFromText($csv, $apiKey, $prompt);
        }

        throw new \InvalidArgumentException("Unsupported file type: {$extension}");
    }

    private function generateFromInline(string $filePath, string $mimeType, string $apiKey, string $prompt): array
    {
        $start    = microtime(true);
        $data     = base64_encode(file_get_contents($filePath));
        $response = Http::timeout(120)
            ->post(self::GENERATE_URL . '?key=' . $apiKey, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $data]],
                        ['text'        => $prompt],
                    ],
                ]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 4096],
            ]);

        Log::debug('[AiDocumentExtractor] generate response', [
            'status'  => $response->status(),
            'elapsed' => round(microtime(true) - $start, 2) . 's',
            'body'    => $response->failed() ? $response->body() : substr($response->body(), 0, 300),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        return $this->parseResponse($response);
    }

    private function generateFromText(string $text, string $apiKey, string $prompt): array
    {
        $start    = microtime(true);
        $response = Http::timeout(120)
            ->post(self::GENERATE_URL . '?key=' . $apiKey, [
                'contents' => [[
                    'parts' => [
                        ['text' => $text . "\n\n" . $prompt],
                    ],
                ]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 4096],
            ]);

        Log::debug('[AiDocumentExtractor] generate response', [
            'status'  => $response->status(),
            'elapsed' => round(microtime(true) - $start, 2) . 's',
            'body'    => $response->failed() ? $response->body() : substr($response->body(), 0, 300),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        return $this->parseResponse($response);
    }

    private function parseResponse($response): array
    {
        $text = $response->json('candidates.0.content.parts.0.text', '');
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode(trim($text), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \RuntimeException('Gemini returned invalid JSON: ' . $text);
        }

        return $decoded;
    }

    private function docxToHtml(string $filePath): string
    {
        $phpWord = WordFactory::load($filePath);
        $tmpFile = tempnam(sys_get_temp_dir(), 'docx_') . '.html';

        try {
            $writer = WordFactory::createWriter($phpWord, 'HTML');
            $writer->save($tmpFile);
            return file_get_contents($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    private function xlsxToCsv(string $filePath): string
    {
        $spreadsheet = SpreadsheetFactory::load($filePath);
        $output      = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $output[] = '=== Sheet: ' . $sheet->getTitle() . ' ===';
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $output[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', $row));
            }
        }

        return implode("\n", $output);
    }

}
