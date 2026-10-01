<?php

namespace App\Services\Realization;

use App\Enums\RealizationStatus;
use App\Models\Realization;
use App\Models\RealizationAttachment;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RealizationAttachmentService
{
    private const NOT_EDITABLE_MESSAGE = 'Bukti dukung hanya dapat diunggah saat realisasi berstatus draft atau dikembalikan.';

    public function __construct(
        private readonly AuditService $auditService,
        private readonly RealizationAccessService $access,
    ) {
    }

    /**
     * Bukti dukung dikunci sejak realisasi diajukan (Phase 11, D-G): reviewer
     * menilai nilai bersama bukti, sehingga bukti tidak boleh berubah selama
     * review. Bukti tambahan ditempuh lewat return, lalu pemilik menambah,
     * lalu resubmit.
     *
     * @return array{0: bool, 1: RealizationAttachment|string, 2: int|null}
     */
    public function store(Realization $realization, UploadedFile $file, User $actor): array
    {
        if (! $this->access->canActAsOwner($realization, $actor)) {
            return [false, 'Anda hanya dapat mengunggah bukti dukung untuk realisasi milik Anda sendiri.', 403];
        }

        // Pemeriksaan awal (fail-fast) SEBELUM file ditulis. Status pada
        // $realization bisa basi; yang menentukan adalah pemeriksaan di bawah lock.
        if (! $this->isEditable($realization->status)) {
            return [false, self::NOT_EDITABLE_MESSAGE, 409];
        }

        $isBackup = $this->access->isBackup($actor);

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("realizations/{$realization->id}", $filename, 'local');

        if (! $path) {
            return [false, 'Gagal menyimpan file.', 500];
        }

        try {
            $result = DB::transaction(function () use ($realization, $path, $actor, $isBackup) {
                $locked = Realization::query()->lockForUpdate()->find($realization->id);

                if ($locked === null) {
                    return [false, 'Realisasi tidak ditemukan.', 404];
                }

                if (! $this->isEditable($locked->status)) {
                    return [false, self::NOT_EDITABLE_MESSAGE, 409];
                }

                $attachment = RealizationAttachment::create([
                    'realization_id' => $locked->id,
                    'file_path'      => $path,
                    'uploaded_by'    => $actor->id,
                ]);

                $this->auditService->log(
                    action: $isBackup ? 'backup_attachment_upload' : 'attachment_upload',
                    entityType: 'RealizationAttachment',
                    entityId: $attachment->id,
                    oldValue: null,
                    newValue: ['realization_id' => $locked->id, 'file_path' => $path],
                    actor: $actor,
                );

                return [true, $attachment, null];
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        // Tuple gagal (status berubah di antara cek awal dan lock): file yang
        // sudah ditulis tidak boleh tertinggal sebagai file yatim.
        if (! $result[0]) {
            Storage::disk('local')->delete($path);
        }

        return $result;
    }

    private function isEditable(?string $status): bool
    {
        return RealizationStatus::tryFrom((string) $status)?->isOwnerEditable() ?? false;
    }
}