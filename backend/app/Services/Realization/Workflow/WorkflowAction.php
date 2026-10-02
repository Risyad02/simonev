<?php

namespace App\Services\Realization\Workflow;

/**
 * Aksi tingkat endpoint. Transisi konkret ditentukan registry dari
 * (status saat ini + aksi), bukan dari request.
 */
enum WorkflowAction: string
{
    case Submit = 'submit';
    case Approve = 'approve';
    case SendBack = 'return';
}