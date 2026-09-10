<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\MasterData\StoreReportingPeriodRequest;
use App\Http\Requests\MasterData\UpdateReportingPeriodRequest;
use App\Models\ReportingPeriod;
use App\Services\MasterData\ReportingPeriodService;

class ReportingPeriodController extends BaseController
{
    public function __construct(protected ReportingPeriodService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar periode pelaporan berhasil diambil');
    }

    public function show(ReportingPeriod $reportingPeriod)
    {
        return $this->success($reportingPeriod, 'Detail periode pelaporan berhasil diambil');
    }

    public function store(StoreReportingPeriodRequest $request)
    {
        $reportingPeriod = $this->service->create($request->validated());

        return $this->success($reportingPeriod, 'Periode pelaporan berhasil dibuat', 201);
    }

    public function update(UpdateReportingPeriodRequest $request, ReportingPeriod $reportingPeriod)
    {
        $reportingPeriod = $this->service->update($reportingPeriod, $request->validated());

        return $this->success($reportingPeriod, 'Periode pelaporan berhasil diperbarui');
    }

    public function destroy(ReportingPeriod $reportingPeriod)
    {
        if (! $this->service->delete($reportingPeriod)) {
            return $this->error(
                'Periode pelaporan tidak dapat dihapus karena masih digunakan pada versi indikator',
                null,
                409
            );
        }

        return $this->success(null, 'Periode pelaporan berhasil dihapus');
    }
}