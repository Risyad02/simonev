<?php

namespace App\Http\Controllers\Api\V1\Realization;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Realization\ApprovalQueueRequest;
use App\Http\Requests\Realization\ApproveRealizationRequest;
use App\Http\Requests\Realization\ReturnRealizationRequest;
use App\Http\Requests\Realization\SubmitRealizationRequest;
use App\Models\Realization;
use App\Services\Realization\RealizationAccessService;
use App\Services\Realization\RealizationApprovalService;
use Illuminate\Http\Request;

class RealizationWorkflowController extends BaseController
{
    public function __construct(
        protected RealizationApprovalService $service,
        protected RealizationAccessService $access,
    ) {
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

    public function approve(ApproveRealizationRequest $request, Realization $realization)
    {
        [$ok, $result, $statusCode] = $this->service->approve(
            $realization,
            $request->user(),
            $request->validated('note')
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Persetujuan tahap berhasil dicatat');
    }

    public function sendBack(ReturnRealizationRequest $request, Realization $realization)
    {
        [$ok, $result, $statusCode] = $this->service->sendBack(
            $realization,
            $request->user(),
            $request->validated('note')
        );

        if (! $ok) {
            return $this->error($result, null, $statusCode);
        }

        return $this->success($result, 'Realisasi berhasil dikembalikan untuk koreksi');
    }

    public function queue(ApprovalQueueRequest $request)
    {
        $paginator = $this->access->approvalQueue(
            $request->user(),
            (int) ($request->validated('per_page') ?: 15)
        );

        return $this->paginated($paginator, 'Antrean persetujuan berhasil diambil');
    }

    public function history(Request $request, Realization $realization)
    {
        if (! $this->access->canView($realization, $request->user())) {
            return $this->error('Anda tidak memiliki akses ke riwayat realisasi ini.', null, 403);
        }

        return $this->success(
            $realization->approvalHistory()->with('actor:id,name')->get(),
            'Riwayat persetujuan berhasil diambil'
        );
    }
}