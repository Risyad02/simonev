<?php

namespace App\Services\Structure;

use App\Models\PerformanceStructure;
use Illuminate\Support\Facades\Auth;

class PerformanceStructureService
{
    public function list()
    {
        return PerformanceStructure::query()->orderBy('level_type')->orderBy('name')->get();
    }

    public function create(array $data): PerformanceStructure
    {
        $data['created_by'] = Auth::id();

        return PerformanceStructure::create($data);
    }

    /**
     * @return array{0: bool, 1: ?string, 2: PerformanceStructure}
     */
    public function update(PerformanceStructure $item, array $data): array
    {
        if (array_key_exists('parent_id', $data) && $this->isCircular($item, $data['parent_id'])) {
            return [false, 'Parent tidak boleh membentuk struktur hierarki melingkar (circular).', $item];
        }

        $item->update($data);

        return [true, null, $item];
    }

    public function isCircular(PerformanceStructure $item, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }

        if ($newParentId === $item->id) {
            return true;
        }

        $current = PerformanceStructure::find($newParentId);

        while ($current) {
            if ($current->id === $item->id) {
                return true;
            }

            $current = $current->parent_id ? PerformanceStructure::find($current->parent_id) : null;
        }

        return false;
    }
}