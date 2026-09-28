<?php

namespace Tests\Feature\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
    }

    public function test_log_stores_all_fields_correctly(): void
    {
        $actor = User::factory()->create();

        $log = $this->auditService->log(
            action: 'backup_operator_input',
            entityType: 'Realization',
            entityId: 42,
            oldValue: null,
            newValue: ['realization_value' => 100, 'status' => 'draft'],
            actor: $actor,
        );

        $this->assertDatabaseHas('audit_logs', [
            'id'          => $log->id,
            'user_id'     => $actor->id,
            'action'      => 'backup_operator_input',
            'entity_type' => 'Realization',
            'entity_id'   => 42,
        ]);
    }

    public function test_old_value_and_new_value_are_cast_to_array(): void
    {
        $log = $this->auditService->log(
            action: 'target_revision_created',
            entityType: 'Target',
            entityId: 7,
            oldValue: ['target_value' => 80],
            newValue: ['target_value' => 90],
            actor: null,
        );

        $fresh = AuditLog::find($log->id);

        $this->assertIsArray($fresh->old_value);
        $this->assertIsArray($fresh->new_value);
        $this->assertSame(['target_value' => 80], $fresh->old_value);
        $this->assertSame(['target_value' => 90], $fresh->new_value);
    }

    public function test_actor_is_nullable(): void
    {
        $log = $this->auditService->log(
            action: 'system_generated_action',
            entityType: 'Target',
            entityId: 1,
            oldValue: null,
            newValue: null,
            actor: null,
        );

        $this->assertNull($log->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'id'      => $log->id,
            'user_id' => null,
        ]);
    }

    public function test_audit_log_table_has_no_updated_at_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('audit_logs', 'updated_at'),
            'audit_logs table must not have an updated_at column — append-only invariant.'
        );

        $this->assertNull(AuditLog::UPDATED_AT);
    }

    public function test_audit_log_rolls_back_with_its_parent_transaction(): void
    {
        $this->assertDatabaseCount('audit_logs', 0);

        try {
            DB::transaction(function () {
                $this->auditService->log(
                    action: 'backup_operator_input',
                    entityType: 'Realization',
                    entityId: 99,
                    oldValue: null,
                    newValue: ['status' => 'draft'],
                    actor: null,
                );

                throw new RuntimeException('Simulated domain transaction failure');
            });
        } catch (RuntimeException) {
            // expected — verifying rollback below
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }
}