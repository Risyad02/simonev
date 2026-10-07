<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\Realization\RealizationAccessService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Titik sambung TUNGGAL scope baca dashboard (Q2/D5). Memilih mode berdasarkan
 * presedensi permission dan mendelegasikan predikat baca ke
 * RealizationAccessService. TBD-4 ditambahkan di RealizationAccessService,
 * bukan di query dashboard.
 */
class DashboardScopeService
{
    public const MODE_FULL = 'full';
    public const MODE_CROSS_UNIT = 'cross-unit';
    public const MODE_OPERATIONAL = 'operational';
    public const MODE_OWN_SCOPE = 'own-scope';
    public const MODE_STRATEGIC_SUMMARY = 'strategic-summary';

    /** Urutan = presedensi (D5): permission paling luas menang. */
    private const PRECEDENCE = [
        'dashboard.view.full'              => self::MODE_FULL,
        'dashboard.view.cross-unit'        => self::MODE_CROSS_UNIT,
        'dashboard.view.operational'       => self::MODE_OPERATIONAL,
        'dashboard.view.own-scope'         => self::MODE_OWN_SCOPE,
        'dashboard.view.strategic-summary' => self::MODE_STRATEGIC_SUMMARY,
    ];

    public function __construct(
        private readonly RealizationAccessService $access,
    ) {
    }

    /** null bila aktor tidak memegang permission dashboard apa pun. */
    public function resolve(User $actor): ?DashboardScope
    {
        foreach (self::PRECEDENCE as $permission => $mode) {
            if ($actor->can($permission)) {
                return $this->scopeFor($mode, $actor);
            }
        }

        return null;
    }

    /**
     * Memasang read-scope pada query dashboard. Nama kolom default mengikuti
     * alias OfficialRealizationQuery. Mode tanpa readScoped tidak diubah.
     */
    public function applyReadScope(
        EloquentBuilder|QueryBuilder $query,
        DashboardScope $scope,
        User $actor,
        string $idColumn = 'w.realization_id',
        string $statusColumn = 'w.status',
        string $inputByColumn = 'w.input_by',
    ): EloquentBuilder|QueryBuilder {
        if (! $scope->readScoped) {
            return $query;
        }

        return $this->access->applyReadScope($query, $actor, $idColumn, $statusColumn, $inputByColumn);
    }

    private function scopeFor(string $mode, User $actor): DashboardScope
    {
        return match ($mode) {
            self::MODE_FULL, self::MODE_CROSS_UNIT, self::MODE_OPERATIONAL => new DashboardScope(
                mode: $mode,
                readScoped: false,
                rowLevel: true,
                coverageAvailable: true,
                includeDataQuality: true,
                transitional: false,
            ),
            // Aktor dengan tahap review (Kasubbid/Kabid) hanya mendapat agregat (G7);
            // diturunkan dari registry workflow, bukan dari nama role.
            self::MODE_OWN_SCOPE => new DashboardScope(
                mode: $mode,
                readScoped: true,
                rowLevel: $this->access->stagesFor($actor) === [],
                coverageAvailable: false,
                includeDataQuality: false,
                transitional: true,
            ),
            default => new DashboardScope(
                mode: $mode,
                readScoped: false,
                rowLevel: false,
                coverageAvailable: true,
                includeDataQuality: false,
                transitional: false,
            ),
        };
    }
}