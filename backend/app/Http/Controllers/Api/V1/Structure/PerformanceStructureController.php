<?php

namespace App\Http\Controllers\Api\V1\Structure;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Structure\StorePerformanceStructureRequest;
use App\Http\Requests\Structure\UpdatePerformanceStructureRequest;
use App\Models\PerformanceStructure;
use App\Services\Structure\PerformanceStructureService;

class PerformanceStructureController extends BaseController
{
    public function __construct(protected PerformanceStructureService $service)
    {
    }

    public function index()
    {
        return $this->success($this->service->list(), 'Daftar struktur kinerja berhasil diambil');
    }

    public function show(PerformanceStructure $performanceStructure)
    {
        return $this->success($performanceStructure, 'Detail struktur kinerja berhasil diambil');
    }

    public function store(StorePerformanceStructureRequest $request)
    {
        $item = $this->service->create($request->validated());

        return $this->success($item, 'Struktur kinerja berhasil dibuat', 201);
    }

    public function update(UpdatePerformanceStructureRequest $request, PerformanceStructure $performanceStructure)
    {
        [$ok, $message, $performanceStructure] = $this->service->update($performanceStructure, $request->validated());

        if (! $ok) {
            return $this->error($message, null, 422);
        }

        return $this->success($performanceStructure, 'Struktur kinerja berhasil diperbarui');
    }
}