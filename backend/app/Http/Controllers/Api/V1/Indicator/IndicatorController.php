<?php

namespace App\Http\Controllers\Api\V1\Indicator;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Indicator\StoreIndicatorRequest;
use App\Http\Requests\Indicator\UpdateIndicatorRequest;
use App\Models\Indicator;
use App\Services\Indicator\IndicatorService;

class IndicatorController extends BaseController
{
    public function __construct(protected IndicatorService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar indikator berhasil diambil');
    }

    public function show(Indicator $indicator)
    {
        return $this->success($indicator, 'Detail indikator berhasil diambil');
    }

    public function store(StoreIndicatorRequest $request)
    {
        $item = $this->service->create($request->validated());

        return $this->success($item, 'Indikator berhasil dibuat', 201);
    }

    public function update(UpdateIndicatorRequest $request, Indicator $indicator)
    {
        $item = $this->service->update($indicator, $request->validated());

        return $this->success($item, 'Indikator berhasil diperbarui');
    }

    public function destroy(Indicator $indicator)
    {
        [$ok, $message] = $this->service->delete($indicator);

        if (! $ok) {
            return $this->error($message, null, 422);
        }

        return $this->success(null, 'Indikator berhasil dihapus');
    }
}