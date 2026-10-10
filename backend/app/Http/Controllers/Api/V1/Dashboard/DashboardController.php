<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Dashboard\AchievementByStructureRequest;
use App\Http\Requests\Dashboard\AchievementIndicatorsRequest;
use App\Http\Requests\Dashboard\DashboardFilterRequest;
use App\Services\Dashboard\DashboardDataQualityService;
use App\Services\Dashboard\DashboardFilterOptionsService;
use App\Services\Dashboard\DashboardIndicatorListService;
use App\Services\Dashboard\DashboardPipelineService;
use App\Services\Dashboard\DashboardScope;
use App\Services\Dashboard\DashboardScopeService;
use App\Services\Dashboard\DashboardStructureService;
use App\Services\Dashboard\DashboardSummaryService;
use App\Services\Dashboard\OfficialRealizationQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly DashboardScopeService $scopes,
        private readonly DashboardSummaryService $summaryService,
        private readonly DashboardPipelineService $pipelineService,
        private readonly DashboardDataQualityService $dataQualityService,
        private readonly DashboardIndicatorListService $indicatorListService,
        private readonly DashboardStructureService $structureService,
        private readonly DashboardFilterOptionsService $filterOptionsService,
    ) {
    }

    public function summary(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopes->resolve($user);

        if ($scope === null) {
            return $this->error('Anda tidak memiliki akses dashboard.', null, 403);
        }

        $filters = $request->filters();

        // G18: filter indikator menyempitkan agregat ke satu indikator, sehingga
        // scope tanpa akses row-level tidak boleh memakainya.
        if (! $scope->rowLevel && array_key_exists('indicator_id', $filters)) {
            return $this->error(
                'Filter indikator tidak tersedia untuk scope Anda.',
                ['indicator_id' => ['Filter indikator tidak tersedia untuk scope Anda.']],
                422,
            );
        }

        return $this->successWithMeta(
            $this->summaryService->summarize($user, $scope, $filters),
            ['basis' => $this->basis($scope, $filters, true)],
        );
    }

    public function pipeline(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopes->resolve($user);

        if ($scope === null) {
            return $this->error('Anda tidak memiliki akses dashboard.', null, 403);
        }

        return $this->successWithMeta(
            $this->pipelineService->pipeline($user, $scope),
            ['basis' => $this->basis($scope, [], false)],
        );
    }

    public function dataQuality(Request $request): JsonResponse
    {
        $scope = $this->scopes->resolve($request->user());

        if ($scope === null || ! $scope->includeDataQuality) {
            return $this->error('Anda tidak memiliki akses ke laporan kualitas data.', null, 403);
        }

        return $this->successWithMeta(
            $this->dataQualityService->report(),
            ['basis' => $this->basis($scope, [], true)],
        );
    }

    public function achievementIndicators(AchievementIndicatorsRequest $request): JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopes->resolve($user);

        if ($scope === null || ! $scope->rowLevel) {
            return $this->error('Anda tidak memiliki akses ke daftar angka resmi per indikator.', null, 403);
        }

        $filters = $request->filters();

        return $this->paginated(
            $this->indicatorListService->paginate($user, $scope, $filters, $request->perPage()),
            'Berhasil',
            200,
            ['basis' => $this->basis($scope, $filters, true)],
        );
    }

    public function achievementByStructure(AchievementByStructureRequest $request): JsonResponse
    {
        $scope = $this->scopes->resolve($request->user());

        if ($scope === null || ! $scope->coverageAvailable) {
            return $this->error('Anda tidak memiliki akses ke agregasi per struktur.', null, 403);
        }

        return $this->successWithMeta(
            $this->structureService->byLevel($request->validated('level')),
            ['basis' => $this->basis($scope, [], true)],
        );
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopes->resolve($user);

        if ($scope === null) {
            return $this->error('Anda tidak memiliki akses dashboard.', null, 403);
        }

        return $this->successWithMeta(
            $this->filterOptionsService->options($user, $scope),
            ['basis' => $this->basis($scope, [], true)],
        );
    }

    /**
     * Dasar perhitungan yang menyertai setiap respons agar konsumen tidak
     * menganggap aturan sementara sebagai definisi permanen.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function basis(DashboardScope $scope, array $filters, bool $official): array
    {
        $basis = [
            'scope' => [
                'mode'         => $scope->mode,
                'transitional' => $scope->transitional,
                'description'  => $scope->transitional
                    ? 'Unit-scope belum tersedia (TBD-4): data dibatasi menurut kepemilikan, tahap workflow, dan riwayat tindakan Anda.'
                    : 'Seluruh data; unit-scope belum tersedia (TBD-4).',
            ],
            'filters'      => $filters,
            'generated_at' => now()->toIso8601String(),
        ];

        if ($official) {
            return [
                'official_rule' => OfficialRealizationQuery::RULE,
                'key'           => OfficialRealizationQuery::KEY,
            ] + $basis;
        }

        return $basis + [
            'note' => 'Pipeline bukan angka resmi: direkap_sekretaris bukan status final; hanya disahkan yang dihitung resmi.',
        ];
    }
}