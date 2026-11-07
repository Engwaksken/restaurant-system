<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;

/**
 * A photo or PDF a visitor attaches in the chat. Kept in memory only: it is
 * sent to the AI provider for this one reply and never stored on the server.
 */
final class ChatAttachment
{
    public const MAX_KILOBYTES = 5120;

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const PDF_TYPE = 'application/pdf';

    private function __construct(
        public readonly string $mimeType,
        public readonly string $base64,
        public readonly string $name,
    ) {
    }

    /** Validation rules for the upload field. */
    public static function rules(): array
    {
        return ['nullable', 'file', 'max:' . self::MAX_KILOBYTES, 'mimetypes:' . implode(',', [...self::IMAGE_TYPES, self::PDF_TYPE])];
    }

    public static function fromUpload(?UploadedFile $file): ?self
    {
        if ($file === null || !$file->isValid()) {
            return null;
        }

        // Trust the detected type, not the browser-supplied one.
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, [...self::IMAGE_TYPES, self::PDF_TYPE], true)) {
            return null;
        }

        return new self($mime, base64_encode((string) file_get_contents($file->getRealPath())), mb_substr($file->getClientOriginalName(), 0, 120));
    }

    public function isImage(): bool
    {
        return in_array($this->mimeType, self::IMAGE_TYPES, true);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === self::PDF_TYPE;
    }

    public function dataUrl(): string
    {
        return 'data:' . $this->mimeType . ';base64,' . $this->base64;
    }
}
