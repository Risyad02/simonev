<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(): AuditLog
    {
        return AuditLog::create([
            'user_id'     => User::factory()->create()->id,
            'action'      => 'test_action',
            'entity_type' => 'Realization',
            'entity_id'   => 1,
            'old_value'   => null,
            'new_value'   => ['status' => 'draft'],
        ]);
    }

    public function test_audit_log_tetap_dapat_dibuat_seperti_sebelumnya(): void
    {
        $log = $this->makeLog();

        $this->assertDatabaseHas('audit_logs', [
            'id'          => $log->id,
            'action'      => 'test_action',
            'entity_type' => 'Realization',
            'entity_id'   => 1,
        ]);
        $this->assertSame(['status' => 'draft'], $log->fresh()->new_value);
    }

    public function test_update_audit_log_ditolak_dan_baris_tetap_utuh(): void
    {
        $log = $this->makeLog();

        try {
            $log->update(['action' => 'diubah']);
            $this->fail('LogicException diharapkan pada update AuditLog.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'test_action']);
        }
    }

    public function test_delete_audit_log_ditolak_dan_baris_tetap_ada(): void
    {
        $log = $this->makeLog();

        try {
            $log->delete();
            $this->fail('LogicException diharapkan pada delete AuditLog.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
        }
    }
}