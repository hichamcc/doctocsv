<?php

require __DIR__ . '/vendor/autoload.php';

$keyFile  = env('GOOGLE_SERVICE_ACCOUNT_JSON', __DIR__ . '/storage/app/google-service-account.json');
$folderId = '1K_jBliwMcQHSx7nkY4CY6lCpwly9AEm-';

$client = new Google\Client();
$client->setAuthConfig($keyFile);
$client->addScope(Google\Service\Drive::DRIVE_READONLY);

$drive = new Google\Service\Drive($client);

$files = $drive->files->listFiles([
    'q'      => "'{$folderId}' in parents and trashed = false",
    'fields' => 'files(id, name, mimeType)',
]);

echo "Files in folder:\n";
foreach ($files->getFiles() as $file) {
    echo "  [{$file->getMimeType()}] {$file->getName()} ({$file->getId()})\n";

    // If it's a spreadsheet, list its sheets (tabs)
    if ($file->getMimeType() === 'application/vnd.google-apps.spreadsheet') {
        $client2 = new Google\Client();
        $client2->setAuthConfig($keyFile);
        $client2->addScope(Google\Service\Sheets::SPREADSHEETS_READONLY);
        $sheets = new Google\Service\Sheets($client2);

        $meta = $sheets->spreadsheets->get($file->getId());
        foreach ($meta->getSheets() as $sheet) {
            echo "      Tab: " . $sheet->getProperties()->getTitle() . "\n";
        }
    }
}
