<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\MasterData\StoreUnitRequest;
use App\Http\Requests\MasterData\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\MasterData\UnitService;

class UnitController extends BaseController
{
    public function __construct(protected UnitService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar unit berhasil diambil');
    }

    public function show(Unit $unit)
    {
        return $this->success($unit, 'Detail unit berhasil diambil');
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = $this->service->create($request->validated());

        return $this->success($unit, 'Unit berhasil dibuat', 201);
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        [$ok, $message, $unit] = $this->service->update($unit, $request->validated());

        if (! $ok) {
            return $this->error($message, null, 422);
        }

        return $this->success($unit, 'Unit berhasil diperbarui');
    }

    public function deactivate(Unit $unit)
    {
        [$ok, $message] = $this->service->deactivate($unit);

        if (! $ok) {
            return $this->error($message, null, 409);
        }

        return $this->success($unit->fresh(), 'Unit berhasil dinonaktifkan');
    }

    public function activate(Unit $unit)
    {
        [$ok, $message] = $this->service->activate($unit);

        if (! $ok) {
            return $this->error($message, null, 409);
        }

        return $this->success($unit->fresh(), 'Unit berhasil diaktifkan');
    }
}