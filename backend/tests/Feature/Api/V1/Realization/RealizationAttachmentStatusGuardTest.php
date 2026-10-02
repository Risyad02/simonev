<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\AuditLog;
use App\Models\Realization;
use App\Models\User;
use App\Services\Realization\RealizationAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationAttachmentStatusGuardTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        Storage::fake('local');
    }

    private function makeRealization(User $owner, string $status): Realization
    {
        return Realization::factory()->ownedBy($owner)->create([
            'target_id' => $this->createActiveTargetWithFormula()->id,
            'status'    => $status,
        ]);
    }

    private function upload(Realization $realization): TestResponse
    {
        return $this->postJson(
            "/api/v1/realizations/{$realization->id}/attachments",
            ['file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]
        );
    }

    public static function statusEditable(): array
    {
        return [
            'draft'        => ['draft'],
            'dikembalikan' => ['dikembalikan'],
        ];
    }

    public static function statusTerkunci(): array
    {
        return [
            'diajukan'            => ['diajukan'],
            'divalidasi_kasubbid' => ['divalidasi_kasubbid'],
            'divalidasi_kabid'    => ['divalidasi_kabid'],
            'direkap_sekretaris'  => ['direkap_sekretaris'],
            'disahkan'            => ['disahkan'],
        ];
    }

    #[DataProvider('statusEditable')]
    public function test_upload_diizinkan_pada_draft_dan_dikembalikan(string $status): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);
        Sanctum::actingAs($owner, ['*']);

        $this->upload($realization)->assertStatus(201);

        $this->assertDatabaseCount('realization_attachments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[DataProvider('statusTerkunci')]
    public function test_upload_ditolak_409_pada_status_terkunci_tanpa_file_dan_tanpa_baris(string $status): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);
        Sanctum::actingAs($owner, ['*']);

        $this->upload($realization)->assertStatus(409);

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('realization_attachments', 0);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_backup_juga_ditolak_409_pada_status_terkunci(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($this->createUserWithRole('admin'), ['*']);

        $this->upload($realization)->assertStatus(409);

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('realization_attachments', 0);
    }

    public function test_pemeriksaan_kepemilikan_mendahului_pemeriksaan_status(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($this->createUserWithRole('operator'), ['*']);

        $this->upload($realization)->assertStatus(403);
    }

    public function test_status_berubah_setelah_cek_awal_menghapus_file_dan_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'draft');
        $stale = Realization::findOrFail($realization->id); // masih draft di memori

        DB::table('realizations')->where('id', $realization->id)->update(['status' => 'diajukan']);

        [$ok, , $code] = app(RealizationAttachmentService::class)->store(
            $stale,
            UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            $owner
        );

        $this->assertFalse($ok);
        $this->assertSame(409, $code);
        $this->assertSame([], Storage::disk('local')->allFiles()); // tidak ada file yatim
        $this->assertDatabaseCount('realization_attachments', 0);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_lampiran_yang_sudah_ada_tetap_dapat_diunduh_setelah_realisasi_terkunci(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'draft');
        Sanctum::actingAs($owner, ['*']);

        $upload = $this->upload($realization)->assertStatus(201);
        $realization->update(['status' => 'disahkan']);

        $this->get("/api/v1/realizations/{$realization->id}/attachments/{$upload->json('data.id')}/download")
            ->assertStatus(200);
    }
}