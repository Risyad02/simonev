<?php

namespace App\Http\Controllers\Api\V1\Indicator;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Indicator\StoreIndicatorVersionRequest;
use App\Models\Indicator;
use App\Models\IndicatorVersion;
use App\Services\Indicator\IndicatorVersionService;

class IndicatorVersionController extends BaseController
{
    public function __construct(protected IndicatorVersionService $service)
    {
    }

    public function index(Indicator $indicator)
    {
        return $this->success($this->service->list($indicator), 'Daftar versi indikator berhasil diambil');
    }

    public function active(Indicator $indicator)
    {
        return $this->success($this->service->active($indicator), 'Versi aktif indikator berhasil diambil');
    }

    public function show(Indicator $indicator, IndicatorVersion $indicatorVersion)
    {
        return $this->success($indicatorVersion, 'Detail versi indikator berhasil diambil');
    }

    public function store(StoreIndicatorVersionRequest $request, Indicator $indicator)
    {
        $item = $this->service->createVersion($indicator, $request->validated());

        return $this->success($item, 'Versi indikator baru berhasil dibuat', 201);
    }
}