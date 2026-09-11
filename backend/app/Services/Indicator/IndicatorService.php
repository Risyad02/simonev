<?php

namespace App\Services\Indicator;

use App\Models\Indicator;

class IndicatorService
{
    public function list()
    {
        return Indicator::query()->orderBy('name')->get();
    }

    public function create(array $data): Indicator
    {
        return Indicator::create($data);
    }

    public function update(Indicator $indicator, array $data): Indicator
    {
        $indicator->update($data);

        return $indicator;
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    public function delete(Indicator $indicator): array
    {
        if ($indicator->versions()->exists()) {
            return [false, 'Indikator tidak dapat dihapus karena sudah memiliki versi konfigurasi.'];
        }

        $indicator->delete();

        return [true, null];
    }
}