<?php

namespace App\Http\Controllers\Api\V1\Realization;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Realization\StoreRealizationAttachmentRequest;
use App\Models\Realization;
use App\Models\RealizationAttachment;
use App\Services\Realization\RealizationAttachmentService;
use Illuminate\Support\Facades\Storage;

class RealizationAttachmentController extends BaseController
{
    public function __construct(protected RealizationAttachmentService $service)
    {
    }

    public function store(StoreRealizationAttachmentRequest $request, Realization $realization)
    {
        [$ok, $result, $statusCode] = $this->service->store(
            $realization,
            $request->file('file'),
            $request->user()
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Bukti dukung berhasil diunggah', 201);
    }

    public function download(Realization $realization, RealizationAttachment $attachment)
    {
        $actor = request()->user();
        $canViewAll = $actor->can('realization.view.cross-unit') || $actor->can('realization.recap.view');
        $isOwner = $realization->input_by === $actor->id;

        if ($attachment->realization_id !== $realization->id) {
            return $this->error('Lampiran tidak ditemukan pada realisasi ini.', null, 404);
        }

        if (! $canViewAll && ! $isOwner) {
            return $this->error('Anda tidak memiliki akses ke lampiran ini.', null, 403);
        }

        return Storage::disk('local')->download($attachment->file_path);
    }
}