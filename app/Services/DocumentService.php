<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploads privately under random names, after inspecting the actual file
 * content. The client-supplied name and type are never trusted.
 */
class DocumentService
{
    private const SIGNATURES = [
        'image/jpeg' => "\xFF\xD8\xFF",
        'image/png' => "\x89PNG\r\n\x1A\n",
        'application/pdf' => '%PDF-',
    ];

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public const DISK = 'local';

    public function store(UploadedFile $file, User $uploader, string $field = 'receipt'): Document
    {
        $mime = $this->inspect($file, $field);
        $name = (string) Str::uuid();
        $path = $file->storeAs('documents/'.now()->format('Y/m'), $name.'.'.self::EXTENSIONS[$mime], self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages([$field => 'The file could not be stored. Please try again.']);
        }

        return Document::query()->create([
            'uuid' => $name,
            'path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName(), self::EXTENSIONS[$mime]),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * Remove a stored file whose database transaction failed.
     */
    public function discard(?Document $document): void
    {
        if ($document !== null) {
            Storage::disk(self::DISK)->delete($document->path);
        }
    }

    private function inspect(UploadedFile $file, string $field): string
    {
        $path = (string) $file->getRealPath();

        try {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) file_get_contents($path));
        } catch (\Throwable) {
            // An unreadable upload, for example one quarantined by antivirus software.
            $mime = '';
        }

        $isAcceptable = match ($mime) {
            'image/jpeg', 'image/png' => $this->hasSignature($path, $mime) && $this->isDecodableImage($path),
            'image/webp' => $this->isWebp($path) && $this->isDecodableImage($path),
            'application/pdf' => $this->hasSignature($path, $mime) && $this->isInertPdf($path),
            default => false,
        };

        if (! $isAcceptable) {
            throw ValidationException::withMessages([$field => 'The file must be a genuine JPG, PNG, WEBP or PDF document.']);
        }

        return $mime;
    }

    private function hasSignature(string $path, string $mime): bool
    {
        $signature = self::SIGNATURES[$mime];

        return file_get_contents($path, false, null, 0, strlen($signature)) === $signature;
    }

    private function isWebp(string $path): bool
    {
        $header = (string) file_get_contents($path, false, null, 0, 12);

        return str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP';
    }

    private function isDecodableImage(string $path): bool
    {
        $size = @getimagesize($path);

        return $size !== false && $size[0] > 0 && $size[1] > 0;
    }

    /**
     * Receipts have no reason to carry scripts or auto-run actions.
     */
    private function isInertPdf(string $path): bool
    {
        return preg_match('~/(JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia)\b~', (string) file_get_contents($path)) !== 1;
    }

    private function safeName(string $name, string $extension): string
    {
        $base = Str::limit(Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'document', 100, '');

        return $base.'.'.$extension;
    }
}
