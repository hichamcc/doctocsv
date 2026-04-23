<?php

namespace App\Http\Controllers;

use App\Services\AiDocumentExtractor;
use App\Services\DocumentToCsvService;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentExtractController extends Controller
{
    private const SESSION_KEY = 'document_extract_records';

    public function __construct(
        private AiDocumentExtractor $extractor,
        private DocumentToCsvService $csvService,
        private GoogleSheetsService $sheetsService,
    ) {}

    public function create()
    {
        return view('document-extract.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'documents'    => ['required', 'array', 'min:1', 'max:10'],
            'documents.*'  => ['required', 'file', 'mimes:pdf,docx,xlsx', 'max:20480'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $instructions = trim($request->input('instructions', ''));
        $allRecords   = [];

        foreach ($request->file('documents') as $file) {
            $path     = $file->store('temp-uploads', 'local');
            $fullPath = Storage::disk('local')->path($path);

            try {
                $records    = $this->extractor->extract($fullPath, $instructions ?: null);
                $allRecords = array_merge($allRecords, $records);
            } finally {
                Storage::disk('local')->delete($path);
            }
        }

        if (empty($allRecords)) {
            return back()->withErrors(['documents' => 'No structured data could be extracted from the uploaded files.']);
        }

        session([self::SESSION_KEY => $allRecords]);

        return redirect()->route('document-extract.preview');
    }

    public function preview()
    {
        $records = session(self::SESSION_KEY, []);

        if (empty($records)) {
            return redirect()->route('document-extract.create');
        }

        $headers = array_keys($records[0]);

        return view('document-extract.preview', compact('records', 'headers'));
    }

    public function sendToSheet(Request $request)
    {
        $request->validate([
            'client_name' => ['required', 'string', 'max:100'],
        ]);

        $records = session(self::SESSION_KEY, []);

        if (empty($records)) {
            return redirect()->route('document-extract.create');
        }

        try {
            $this->sheetsService->append($records, $request->input('client_name'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()])->withInput();
        }

        return back()->with('sheet_success', 'Data sent to Google Sheet successfully.');
    }

    public function download()
    {
        $records = session(self::SESSION_KEY, []);

        if (empty($records)) {
            return redirect()->route('document-extract.create');
        }

        $csv      = $this->csvService->convert($records);
        $filename = 'extracted-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

}
