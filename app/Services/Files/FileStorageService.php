<?php

namespace App\Services\Files;

use App\Contracts\FileScanner;
use App\Enums\FileScanStatus;
use App\Enums\FileVisibility;
use App\Models\File as ManagedFile;
use App\Models\FileRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class FileStorageService
{
    public function __construct(
        private readonly FileScanner $scanner
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function store(
        UploadedFile $upload,
        User $actor,
        Model $related,
        array $attributes = []
    ): ManagedFile {
        $disk = (string) config(
            'file_management.disk',
            'private'
        );

        if (! in_array(
            $disk,
            config('file_management.allowed_disks', []),
            true
        )) {
            throw new LogicException(
                'Managed file disk is not approved for private storage.'
            );
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower(
            (string) ($upload->guessExtension() ?: 'bin')
        );
        $storedName = $uuid.'.'.$extension;
        $directory = sprintf(
            'uploads/%s/%s',
            now()->format('Y/m'),
            $uuid
        );
        $originalName = basename(
            str_replace(
                '\\',
                '/',
                $upload->getClientOriginalName()
            )
        );
        $checksum = hash_file(
            'sha256',
            $upload->getRealPath()
        );

        if (! is_string($checksum)) {
            throw ValidationException::withMessages([
                'file' => ['محاسبه صحت فایل انجام نشد.'],
            ]);
        }

        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $upload,
            $storedName
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => ['ذخیره فایل انجام نشد.'],
            ]);
        }

        try {
            $scanStatus = $this->scanner->scan(
                Storage::disk($disk)->path($path)
            );

            if ($scanStatus !== FileScanStatus::Clean) {
                Storage::disk($disk)->delete($path);

                throw ValidationException::withMessages([
                    'file' => [
                        $scanStatus === FileScanStatus::Infected
                            ? 'فایل آلوده تشخیص داده شد.'
                            : 'بررسی امنیتی فایل ناموفق بود.',
                    ],
                ]);
            }

            return DB::transaction(
                function () use (
                    $actor,
                    $attributes,
                    $checksum,
                    $disk,
                    $extension,
                    $originalName,
                    $path,
                    $related,
                    $scanStatus,
                    $storedName,
                    $upload,
                    $uuid
                ): ManagedFile {
                    $file = ManagedFile::query()->create([
                        'uuid' => $uuid,
                        'uploaded_by' => $actor->getKey(),
                        'disk' => $disk,
                        'visibility' => FileVisibility::Private,
                        'path' => $path,
                        'stored_name' => $storedName,
                        'original_name' => $originalName,
                        'extension' => $extension,
                        'mime_type' => $upload->getMimeType(),
                        'size' => $upload->getSize(),
                        'checksum' => $checksum,
                        'category' =>
                            $attributes['category'] ?? 'other',
                        'is_confidential' => (bool) (
                            $attributes['is_confidential'] ?? false
                        ),
                        'scan_status' => $scanStatus,
                        'scanned_at' => now(),
                        'expires_at' =>
                            $attributes['expires_at'] ?? null,
                        'metadata' => [
                            'source' => 'api',
                        ],
                    ]);

                    FileRelation::query()->create([
                        'file_id' => $file->getKey(),
                        'related_type' => $related->getMorphClass(),
                        'related_id' => $related->getKey(),
                        'purpose' => $attributes['purpose'] ?? null,
                    ]);

                    return $file->load('fileRelations');
                }
            );
        } catch (Throwable $exception) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    public function delete(ManagedFile $file): void
    {
        $disk = $file->disk;
        $path = $file->path;

        DB::transaction(function () use ($file): void {
            $file->fileRelations()->delete();
            $file->delete();
        });

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
