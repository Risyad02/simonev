<?php

namespace App\Http\Controllers\Api\V1\Realization;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Realization\SubmitRealizationRequest;
use App\Models\Realization;
use App\Services\Realization\RealizationApprovalService;

class RealizationWorkflowController extends BaseController
{
    public function __construct(protected RealizationApprovalService $service)
    {
    }

    public function submit(SubmitRealizationRequest $request, Realization $realization)
    {
        [$ok, $result, $statusCode] = $this->service->submit(
            $realization,
            $request->user(),
            $request->validated('note')
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Realisasi berhasil diajukan');
    }
}