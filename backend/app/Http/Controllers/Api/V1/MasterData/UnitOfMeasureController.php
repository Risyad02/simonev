<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\MasterData\StoreUnitOfMeasureRequest;
use App\Http\Requests\MasterData\UpdateUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use App\Services\MasterData\UnitOfMeasureService;

class UnitOfMeasureController extends BaseController
{
    public function __construct(protected UnitOfMeasureService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar satuan berhasil diambil');
    }

    public function show(UnitOfMeasure $unitOfMeasure)
    {
        return $this->success($unitOfMeasure, 'Detail satuan berhasil diambil');
    }

    public function store(StoreUnitOfMeasureRequest $request)
    {
        $unitOfMeasure = $this->service->create($request->validated());

        return $this->success($unitOfMeasure, 'Satuan berhasil dibuat', 201);
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure)
    {
        $unitOfMeasure = $this->service->update($unitOfMeasure, $request->validated());

        return $this->success($unitOfMeasure, 'Satuan berhasil diperbarui');
    }

    public function destroy(UnitOfMeasure $unitOfMeasure)
    {
        if (! $this->service->delete($unitOfMeasure)) {
            return $this->error(
                'Satuan tidak dapat dihapus karena masih digunakan pada versi indikator',
                null,
                409
            );
        }

        return $this->success(null, 'Satuan berhasil dihapus');
    }
}