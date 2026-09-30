<?php

namespace Tests\Unit\Realization;

use App\Enums\ApprovalHistoryAction;
use PHPUnit\Framework\TestCase;

class ApprovalHistoryActionTest extends TestCase
{
    public function test_kosakata_action_history_tepat_enam(): void
    {
        $values = array_map(fn (ApprovalHistoryAction $a) => $a->value, ApprovalHistoryAction::cases());

        $this->assertEqualsCanonicalizing(
            ['submit', 'validate', 'recap', 'approve', 'reject', 'koreksi'],
            $values
        );
    }
}