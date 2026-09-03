<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\HealthCheckService;

class HealthController extends BaseController
{
    public function check(HealthCheckService $service)
    {
        return $this->success(
            $service->getStatus(),
            'API SIMONEV berjalan normal'
        );
    }
}