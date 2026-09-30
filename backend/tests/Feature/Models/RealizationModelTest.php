<?php

namespace Tests\Feature\Models;

use App\Enums\RealizationStatus;
use App\Models\Realization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;
use App\Models\User;

class RealizationModelTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    private function makeRealization(array $override = []): Realization
    {
        $target = $this->createActiveTargetWithFormula();

        return Realization::factory()->create(array_merge(['target_id' => $target->id], $override));
    }

    public function test_factory_membuat_realisasi_draft_dengan_target_yang_di_override(): void
    {
        $target = $this->createActiveTargetWithFormula();
        $realization = Realization::factory()->create(['target_id' => $target->id]);

        $this->assertSame($target->id, $realization->target_id);
        $this->assertSame('draft', $realization->status);
        $this->assertNull($realization->input_by);
        $this->assertDatabaseHas('realizations', ['id' => $realization->id, 'status' => 'draft']);
    }

    public function test_factory_state_withstatus_dan_ownedby(): void
    {
        $owner = User::factory()->create();
        $target = $this->createActiveTargetWithFormula();

        $realization = Realization::factory()
            ->withStatus(RealizationStatus::Diajukan)
            ->ownedBy($owner)
            ->create(['target_id' => $target->id]);

        $this->assertSame('diajukan', $realization->status);
        $this->assertSame($owner->id, $realization->input_by);
    }

    public function test_accessor_muncul_di_array_untuk_status_draft(): void
    {
        $array = $this->makeRealization()->toArray();

        $this->assertArrayHasKey('is_final', $array);
        $this->assertArrayHasKey('status_label', $array);
        $this->assertFalse($array['is_final']);
        $this->assertSame('Draft', $array['status_label']);
    }

    public function test_hanya_disahkan_yang_is_final_true(): void
    {
        foreach (RealizationStatus::cases() as $status) {
            $realization = $this->makeRealization(['status' => $status->value]);

            $this->assertSame(
                $status === RealizationStatus::Disahkan,
                $realization->is_final,
                "is_final salah untuk {$status->value}"
            );
            $this->assertSame($status->label(), $realization->status_label);
        }
    }

    public function test_direkap_sekretaris_jelas_bukan_final(): void
    {
        $realization = $this->makeRealization(['status' => 'direkap_sekretaris']);

        $this->assertFalse($realization->is_final);
        $this->assertStringContainsString('menunggu pengesahan', $realization->status_label);
    }

    public function test_status_tidak_dikenal_tidak_melempar_exception(): void
    {
        $realization = $this->makeRealization(['status' => 'status_lama_tak_dikenal']);

        $this->assertFalse($realization->is_final);
        $this->assertSame('status_lama_tak_dikenal', $realization->status_label);
    }

    public function test_relasi_approval_history_bertipe_hasmany_dan_awalnya_kosong(): void
    {
        $realization = $this->makeRealization();

        $this->assertInstanceOf(HasMany::class, $realization->approvalHistory());
        $this->assertCount(0, $realization->approvalHistory);
    }
}