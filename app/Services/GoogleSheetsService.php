<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Sheets;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\Color;
use Google\Service\Sheets\GridRange;
use Google\Service\Sheets\RepeatCellRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\TextFormat;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsService
{
    private Drive $drive;
    private Sheets $sheets;
    private string $rootFolderId;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google.service_account_json'));
        $client->addScope(Drive::DRIVE);
        $client->addScope(Sheets::SPREADSHEETS);

        $this->drive        = new Drive($client);
        $this->sheets       = new Sheets($client);
        $this->rootFolderId = config('services.google.drive_folder_id');

    }

    public function append(array $records, string $clientName): void
    {
        $year = date('Y');
        $week = date('W');
        $day  = date('l'); // Monday, Tuesday, etc.

        $yearFolderId  = $this->findFolder($year, $this->rootFolderId);
        $spreadsheetId = $this->findSpreadsheet('Week ' . $week, $yearFolderId);
        $sheetId       = $this->findOrCreateSheet($spreadsheetId, $day);

        $this->appendBlock($spreadsheetId, $sheetId, $day, $records, $clientName);
    }

    private function findFolder(string $name, string $parentId): string
    {
        $safeName = addslashes($name);
        $results  = $this->drive->files->listFiles([
            'q'      => "name='{$safeName}' and '{$parentId}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            'fields' => 'files(id)',
        ]);

        if (count($results->getFiles()) === 0) {
            throw new \RuntimeException("Folder \"{$name}\" not found. Please create it in Google Drive first.");
        }

        return $results->getFiles()[0]->getId();
    }

    private function findSpreadsheet(string $name, string $parentId): string
    {
        $safeName = addslashes($name);
        $results  = $this->drive->files->listFiles([
            'q'      => "name='{$safeName}' and '{$parentId}' in parents and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            'fields' => 'files(id)',
        ]);

        if (count($results->getFiles()) === 0) {
            throw new \RuntimeException("Spreadsheet \"{$name}\" not found. Please create it in Google Drive first.");
        }

        return $results->getFiles()[0]->getId();
    }

    private function findOrCreateSheet(string $spreadsheetId, string $sheetName): int
    {
        $spreadsheet = $this->sheets->spreadsheets->get($spreadsheetId);

        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === $sheetName) {
                return $sheet->getProperties()->getSheetId();
            }
        }

        $response = $this->sheets->spreadsheets->batchUpdate(
            $spreadsheetId,
            new BatchUpdateSpreadsheetRequest([
                'requests' => [new SheetsRequest([
                    'addSheet' => new AddSheetRequest([
                        'properties' => new SheetProperties(['title' => $sheetName]),
                    ]),
                ])],
            ])
        );

        return $response->getReplies()[0]->getAddSheet()->getProperties()->getSheetId();
    }

    private function appendBlock(string $spreadsheetId, int $sheetId, string $sheetName, array $records, string $clientName): void
    {
        // Find next empty row
        try {
            $existing = $this->sheets->spreadsheets_values->get($spreadsheetId, "{$sheetName}!A:A");
            $startRow = count($existing->getValues() ?? []) + 1;
        } catch (\Exception) {
            $startRow = 1;
        }

        $headers  = array_keys($records[0]);
        $colCount = count($headers);

        // Build rows: separator + headers + data
        $rows   = [];
        $rows[] = [$clientName . ' — ' . now()->format('d/m/Y H:i')];
        $rows[] = $headers;
        foreach ($records as $record) {
            $rows[] = array_values(array_map(fn($v) => $v ?? '', $record));
        }

        $this->sheets->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!A{$startRow}",
            new ValueRange(['values' => $rows]),
            ['valueInputOption' => 'RAW']
        );

        $separatorIdx = $startRow - 1; // 0-based
        $headerIdx    = $startRow;     // 0-based

        $this->sheets->spreadsheets->batchUpdate(
            $spreadsheetId,
            new BatchUpdateSpreadsheetRequest(['requests' => [
                // Colored separator row (blue background, white bold text)
                new SheetsRequest([
                    'repeatCell' => new RepeatCellRequest([
                        'range' => new GridRange([
                            'sheetId'          => $sheetId,
                            'startRowIndex'    => $separatorIdx,
                            'endRowIndex'      => $separatorIdx + 1,
                            'startColumnIndex' => 0,
                            'endColumnIndex'   => max($colCount, 10),
                        ]),
                        'cell'   => new CellData([
                            'userEnteredFormat' => new CellFormat([
                                'backgroundColor' => new Color(['red' => 0.259, 'green' => 0.522, 'blue' => 0.957]),
                                'textFormat'      => new TextFormat([
                                    'bold'            => true,
                                    'fontSize'        => 11,
                                    'foregroundColor' => new Color(['red' => 1, 'green' => 1, 'blue' => 1]),
                                ]),
                            ]),
                        ]),
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
                    ]),
                ]),
                // Grey bold header row
                new SheetsRequest([
                    'repeatCell' => new RepeatCellRequest([
                        'range' => new GridRange([
                            'sheetId'          => $sheetId,
                            'startRowIndex'    => $headerIdx,
                            'endRowIndex'      => $headerIdx + 1,
                            'startColumnIndex' => 0,
                            'endColumnIndex'   => $colCount,
                        ]),
                        'cell'   => new CellData([
                            'userEnteredFormat' => new CellFormat([
                                'backgroundColor' => new Color(['red' => 0.9, 'green' => 0.9, 'blue' => 0.9]),
                                'textFormat'      => new TextFormat(['bold' => true]),
                            ]),
                        ]),
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
                    ]),
                ]),
            ]])
        );
    }
}
