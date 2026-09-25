<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Rules for document uploads, shared by employee and requirement documents so
 * the two never drift apart.
 */
final class Uploads
{
    /** Kilobytes, as Laravel's `max` file rule counts them. */
    public const MAX_KB = 10240;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md'];

    /**
     * @return array<int, string>
     */
    public static function documentRule(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES)];
    }

    /**
     * The metadata stored beside a file. The original name is kept only as a
     * label; the file itself goes under a random name, so nothing a user types
     * ever becomes part of a path on disk.
     *
     * @return array{original_name: string, mime_type: string, size: int}
     */
    public static function meta(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }
}
