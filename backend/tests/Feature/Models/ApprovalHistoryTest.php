<?php

namespace Tests\Feature\Models;

use App\Models\ApprovalHistory;
use App\Models\Realization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class ApprovalHistoryTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    private function makeRealization(): Realization
    {
        $target = $this->createActiveTargetWithFormula();

        return Realization::factory()->create(['target_id' => $target->id]);
    }

    private function makeHistory(Realization $realization, ?User $actor = null, array $override = []): ApprovalHistory
    {
        return ApprovalHistory::create(array_merge([
            'realization_id' => $realization->id,
            'actor_id'       => ($actor ?? User::factory()->create())->id,
            'from_status'    => 'draft',
            'to_status'      => 'diajukan',
            'action'         => 'submit',
            'note'           => 'catatan awal',
            'acted_at'       => now(),
        ], $override));
    }

    public function test_history_dapat_dibuat_dengan_tabel_dan_timestamp_yang_benar(): void
    {
        $history = $this->makeHistory($this->makeRealization());

        $this->assertSame('approval_history', $history->getTable());
        $this->assertDatabaseHas('approval_history', [
            'id'          => $history->id,
            'from_status' => 'draft',
            'to_status'   => 'diajukan',
            'action'      => 'submit',
            'note'        => 'catatan awal',
        ]);
        $this->assertInstanceOf(CarbonInterface::class, $history->acted_at);
        $this->assertNotNull($history->fresh()->created_at);
        $this->assertNull($history->fresh()->updated_at);
    }

    public function test_update_history_ditolak_dan_baris_tetap_utuh(): void
    {
        $history = $this->makeHistory($this->makeRealization());

        try {
            $history->update(['note' => 'diubah']);
            $this->fail('LogicException diharapkan pada update ApprovalHistory.');
        } catch (LogicException) {
            $this->assertDatabaseHas('approval_history', ['id' => $history->id, 'note' => 'catatan awal']);
        }
    }

    public function test_delete_history_ditolak_dan_baris_tetap_ada(): void
    {
        $history = $this->makeHistory($this->makeRealization());

        try {
            $history->delete();
            $this->fail('LogicException diharapkan pada delete ApprovalHistory.');
        } catch (LogicException) {
            $this->assertDatabaseHas('approval_history', ['id' => $history->id]);
        }
    }

    public function test_relasi_realization_dan_actor_berfungsi(): void
    {
        $realization = $this->makeRealization();
        $actor = User::factory()->create();
        $history = $this->makeHistory($realization, $actor);

        $this->assertSame($realization->id, $history->realization->id);
        $this->assertSame($actor->id, $history->actor->id);
    }

    public function test_relasi_approval_history_pada_realization_terurut_kronologis(): void
    {
        $realization = $this->makeRealization();

        $this->makeHistory($realization, null, ['from_status' => 'draft', 'to_status' => 'diajukan']);
        $this->makeHistory($realization, null, ['from_status' => 'diajukan', 'to_status' => 'divalidasi_kasubbid', 'action' => 'validate']);

        $this->assertSame(
            ['diajukan', 'divalidasi_kasubbid'],
            $realization->approvalHistory()->pluck('to_status')->all()
        );
    }
}