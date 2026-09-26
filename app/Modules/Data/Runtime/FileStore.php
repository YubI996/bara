<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penyimpanan lampiran (docs/05 §4 kontrol 4): nama acak, MIME dari deteksi server,
 * SHA-256, status pindai. Berkas hanya boleh diunduh bila scan_status = clean.
 */
final readonly class FileStore
{
    public function __construct(
        private ConnectionInterface $db,
        private Filesystems $filesystems,
    ) {}

    public function store(UploadedFile $file, string $objectId, string $fieldKey, string $classification, string $userId): string
    {
        $disk = config()->string('bara.files.disk');
        $id = Str::uuid7()->toString();
        $extension = strtolower($file->getClientOriginalExtension());
        $path = 'records/'.substr($objectId, 0, 8).'/'.$id.($extension !== '' ? '.'.$extension : '');
        $realPath = $file->getRealPath();

        if ($realPath === false || $this->filesystems->disk($disk)->putFileAs(dirname($path), $file, basename($path)) === false) {
            throw new RuntimeException('Gagal menyimpan berkas.');
        }

        $this->db->table('files')->insert([
            'id' => $id,
            'object_id' => $objectId,
            'field_key' => $fieldKey,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $realPath),
            'scan_status' => config()->string('bara.files.scanner') === 'none' ? 'clean' : 'pending',
            'classification' => $classification,
            'uploaded_by' => $userId,
        ]);

        return $id;
    }

    /**
     * Id berkas yang benar-benar milik objek & field ini (mencegah menempelkan berkas orang lain).
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function ownedBy(string $objectId, string $fieldKey, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_filter($this->db->table('files')
            ->where('object_id', $objectId)->where('field_key', $fieldKey)->whereIn('id', $ids)
            ->pluck('id')->all(), 'is_string'));
    }

    /**
     * Info berkas milik objek, berkunci id.
     *
     * @return array<string, array{id: string, name: string, size: int, available: bool, field_key: string|null}>
     */
    public function forObject(string $objectId): array
    {
        $info = [];
        foreach ($this->db->table('files')->where('object_id', $objectId)->get(['id', 'original_name', 'size_bytes', 'scan_status', 'field_key']) as $file) {
            if (! is_string($file->id ?? null)) {
                continue;
            }
            $info[$file->id] = [
                'id' => $file->id,
                'name' => is_string($file->original_name ?? null) ? $file->original_name : 'berkas',
                'size' => is_numeric($file->size_bytes ?? null) ? (int) $file->size_bytes : 0,
                'available' => ($file->scan_status ?? null) === 'clean',
                'field_key' => is_string($file->field_key ?? null) ? $file->field_key : null,
            ];
        }

        return $info;
    }

    /** @return array{disk: string, path: string, name: string, field_key: string, clean: bool}|null */
    public function find(string $fileId, string $objectId): ?array
    {
        $file = $this->db->table('files')->where('id', $fileId)->where('object_id', $objectId)->first();

        if (! is_object($file) || ! is_string($file->disk ?? null) || ! is_string($file->path ?? null) || ! is_string($file->field_key ?? null)) {
            return null;
        }

        return [
            'disk' => $file->disk,
            'path' => $file->path,
            'name' => is_string($file->original_name ?? null) ? $file->original_name : 'berkas',
            'field_key' => $file->field_key,
            'clean' => ($file->scan_status ?? null) === 'clean',
        ];
    }
}
