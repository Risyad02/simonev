<?php

namespace App\Services\Indicator;

use App\Models\Indicator;
use App\Models\IndicatorVersion;
use Illuminate\Support\Facades\DB;

class IndicatorVersionService
{
    public function list(Indicator $indicator)
    {
        return $indicator->versions()->orderByDesc('valid_from')->get();
    }

    public function active(Indicator $indicator): ?IndicatorVersion
    {
        return $indicator->activeVersion;
    }

    public function createVersion(Indicator $indicator, array $data): IndicatorVersion
    {
        return DB::transaction(function () use ($indicator, $data) {
            $current = $indicator->versions()->where('is_active', true)->first();

            if ($current) {
                $current->update([
                    'is_active' => false,
                    'valid_to' => now(),
                ]);
            }

            $data['indicator_id'] = $indicator->id;
            $data['is_active'] = true;
            $data['valid_from'] = $data['valid_from'] ?? now();

            return IndicatorVersion::create($data);
        });
    }
}