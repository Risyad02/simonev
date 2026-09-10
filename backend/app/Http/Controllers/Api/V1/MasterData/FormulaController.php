<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\MasterData\StoreFormulaRequest;
use App\Http\Requests\MasterData\UpdateFormulaRequest;
use App\Models\Formula;
use App\Services\MasterData\FormulaService;

class FormulaController extends BaseController
{
    public function __construct(protected FormulaService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar formula berhasil diambil');
    }

    public function show(Formula $formula)
    {
        return $this->success($formula, 'Detail formula berhasil diambil');
    }

    public function store(StoreFormulaRequest $request)
    {
        $formula = $this->service->create($request->validated());

        return $this->success($formula, 'Formula berhasil dibuat', 201);
    }

    public function update(UpdateFormulaRequest $request, Formula $formula)
    {
        $formula = $this->service->update($formula, $request->validated());

        return $this->success($formula, 'Formula berhasil diperbarui');
    }

    public function destroy(Formula $formula)
    {
        if (! $this->service->delete($formula)) {
            return $this->error(
                'Formula tidak dapat dihapus karena masih digunakan pada versi indikator',
                null,
                409
            );
        }

        return $this->success(null, 'Formula berhasil dihapus');
    }
}