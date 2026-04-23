<?php

require __DIR__ . '/vendor/autoload.php';

$keyFile  = __DIR__ . '/storage/app/google-service-account.json';
$folderId = '1K_jBliwMcQHSx7nkY4CY6lCpwly9AEm-';

$client = new Google\Client();
$client->setAuthConfig($keyFile);
$client->addScope(Google\Service\Drive::DRIVE);

$drive = new Google\Service\Drive($client);

$results = $drive->files->listFiles([
    'q'      => "'{$folderId}' in parents and trashed=false",
    'fields' => 'files(id, name)',
]);

foreach ($results->getFiles() as $file) {
    echo "Deleting: {$file->getName()} ({$file->getId()})\n";
    $drive->files->delete($file->getId());
}

echo "Done.\n";
