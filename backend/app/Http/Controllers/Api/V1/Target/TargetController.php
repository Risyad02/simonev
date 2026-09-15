<?php

namespace App\Http\Controllers\Api\V1\Target;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Target\StoreTargetRequest;
use App\Http\Requests\Target\StoreTargetRevisionRequest;
use App\Models\Target;
use App\Services\Target\TargetService;

class TargetController extends BaseController
{
    public function __construct(protected TargetService $service)
    {
    }

    public function index()
    {
        return $this->success(Target::all(), 'Daftar target berhasil diambil');
    }

    public function show(Target $target)
    {
        return $this->success($target, 'Detail target berhasil diambil');
    }

    public function revisions(Target $target)
    {
        $history = Target::query()
            ->where('indicator_version_id', $target->indicator_version_id)
            ->where('period_label', $target->period_label)
            ->orderBy('revision_no')
            ->get();

        return $this->success($history, 'Riwayat revisi target berhasil diambil');
    }

    public function store(StoreTargetRequest $request)
    {
        [$ok, $result, $statusCode] = $this->service->createInitial(
            $request->validated(),
            $request->user()
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Target berhasil dibuat', 201);
    }

    public function storeRevision(StoreTargetRevisionRequest $request, Target $target)
    {
        [$ok, $result, $statusCode] = $this->service->createRevision(
            $target,
            $request->validated(),
            $request->user()
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Revisi target berhasil dibuat', 201);
    }

    public function destroy(Target $target)
    {
        [$ok, $result, $statusCode] = $this->service->delete($target);

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success(null, $result);
    }
}