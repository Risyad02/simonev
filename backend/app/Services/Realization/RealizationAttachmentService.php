<?php

namespace App\Services\Realization;

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
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @return array{0: bool, 1: RealizationAttachment|string, 2: int|null}
     */
    public function store(Realization $realization, UploadedFile $file, User $actor): array
    {
        $isBackup = ! $actor->can('realization.manage') && $actor->can('realization.manage.backup');
        $isOwner = $realization->input_by === $actor->id;

        if (! $isBackup && ! $isOwner) {
            return [false, 'Anda hanya dapat mengunggah bukti dukung untuk realisasi milik Anda sendiri.', 403];
        }

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("realizations/{$realization->id}", $filename, 'local');

        if (! $path) {
            return [false, 'Gagal menyimpan file.', 500];
        }

        try {
            return DB::transaction(function () use ($realization, $path, $actor, $isBackup) {
                $attachment = RealizationAttachment::create([
                    'realization_id' => $realization->id,
                    'file_path'      => $path,
                    'uploaded_by'    => $actor->id,
                ]);

                $this->auditService->log(
                    action: $isBackup ? 'backup_attachment_upload' : 'attachment_upload',
                    entityType: 'RealizationAttachment',
                    entityId: $attachment->id,
                    oldValue: null,
                    newValue: ['realization_id' => $realization->id, 'file_path' => $path],
                    actor: $actor,
                );

                return [true, $attachment, null];
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}