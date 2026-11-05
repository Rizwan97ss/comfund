<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * The only way to reach an uploaded file. The access is audited before any byte
     * is served, and if the audit entry cannot be written the file is not served.
     */
    public function show(Document $document, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view', $document);

        $audit->record('document.downloaded', 'access', subject: $document, payload: ['name' => $document->original_name, 'mime' => $document->mime_type]);

        return Storage::disk(DocumentService::DISK)->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
