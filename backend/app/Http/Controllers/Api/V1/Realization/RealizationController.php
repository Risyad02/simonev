<?php

namespace App\Http\Controllers\Api\V1\Realization;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Realization\StoreRealizationRequest;
use App\Models\Realization;
use App\Services\Realization\RealizationService;
use Illuminate\Http\Request;

class RealizationController extends BaseController
{
    public function __construct(protected RealizationService $service)
    {
    }

    public function index(Request $request)
    {
        $result = $this->service->listFor($request->user());

        if ($result === null) {
            return $this->error(
                'Filter unit kerja belum tersedia untuk role Anda (menunggu penyelesaian TBD-4 CR-001). Hubungi Admin untuk data lintas unit.',
                null,
                403
            );
        }

        return $this->success($result, 'Daftar realisasi berhasil diambil');
    }

    public function show(Request $request, Realization $realization)
    {
        $actor = $request->user();
        $canViewAll = $actor->can('realization.view.cross-unit') || $actor->can('realization.recap.view');
        $isOwner = $realization->input_by === $actor->id;

        if (! $canViewAll && ! $isOwner) {
            return $this->error('Anda tidak memiliki akses ke realisasi ini.', null, 403);
        }

        return $this->success($realization, 'Detail realisasi berhasil diambil');
    }

    public function store(StoreRealizationRequest $request)
    {
        [$ok, $result, $statusCode] = $this->service->create(
            $request->validated(),
            $request->user()
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Realisasi berhasil dicatat', 201);
    }
}