<?php

namespace App\Enums;

/**
 * Kosakata kolom approval_history.action. "koreksi" adalah tindakan proses
 * bisnis (return oleh Sekretaris), bukan status lifecycle.
 */
enum ApprovalHistoryAction: string
{
    case Submit = 'submit';
    case Validate = 'validate';
    case Recap = 'recap';
    case Approve = 'approve';
    case Reject = 'reject';
    case Koreksi = 'koreksi';
}