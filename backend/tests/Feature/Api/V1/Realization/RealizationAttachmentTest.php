<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\Formula;
use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\Realization;
use App\Models\ReportingPeriod;
use App\Models\Target;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealizationAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        Storage::fake('local');
    }

    private function actingAsOperator(): User
    {
        $user = User::factory()->create();
        $user->assignRole('operator');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsSekretaris(): User
    {
        $user = User::factory()->create();
        $user->assignRole('sekretaris');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createTarget(): Target
    {
        $satuan = UnitOfMeasure::create(['name' => 'Satuan Lampiran Test '.uniqid()]);
        $formula = Formula::create([
            'name' => 'Formula Lampiran Test '.uniqid(),
            'formula_type' => 'persentase_capaian',
            'type' => 'system',
        ]);
        $periode = ReportingPeriod::create(['name' => 'Periode Lampiran Test '.uniqid(), 'periods_per_year' => 12]);
        $planningDocument = PlanningDocument::create([
            'document_type' => 'Renstra',
            'year' => 2026,
            'period_start_year' => 2026,
            'period_end_year' => 2030,
            'version_no' => 1,
            'status' => 'active',
        ]);

        $structureId = DB::table('performance_structure')->insertGetId([
            'level_type' => 'tujuan',
            'name' => 'Struktur Lampiran Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = DB::table('indicators')->insertGetId([
            'structure_id' => $structureId,
            'name' => 'Indikator Lampiran Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorVersion = IndicatorVersion::create([
            'indicator_id' => $indicatorId,
            'unit_of_measure_id' => $satuan->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $periode->id,
            'is_active' => true,
            'valid_from' => now(),
        ]);

        return Target::create([
            'indicator_version_id' => $indicatorVersion->id,
            'planning_document_id' => $planningDocument->id,
            'period_label' => 'Tahun Test',
            'target_value' => 100,
            'revision_no' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'created_by' => null,
        ]);
    }

    private function createRealizationAsOperator(User $operator): Realization
    {
        Sanctum::actingAs($operator, ['*']);
        $target = $this->createTarget();

        $response = $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ]);

        return Realization::find($response->json('data.id'));
    }

    // ── UPLOAD ─────────────────────────────────────────────────────────

    public function test_owner_operator_dapat_mengunggah_bukti_dukung_pdf(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $attachmentPath = $response->json('data.file_path');
        Storage::disk('local')->assertExists($attachmentPath);

        $this->assertDatabaseHas('realization_attachments', [
            'realization_id' => $realization->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'RealizationAttachment',
            'action' => 'attachment_upload',
        ]);
    }

    public function test_filename_yang_disimpan_berupa_uuid_bukan_nama_asli(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('nama-file-asli-rahasia.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ]);

        $path = $response->json('data.file_path');
        $this->assertStringNotContainsString('nama-file-asli-rahasia', $path);
        $this->assertStringEndsWith('.pdf', $path);
    }

    public function test_admin_backup_upload_tercatat_dengan_action_berbeda(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        $this->actingAsAdmin();
        $file = UploadedFile::fake()->create('bukti-admin.pdf', 500, 'application/pdf');

        $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ])->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'RealizationAttachment',
            'action' => 'backup_attachment_upload',
        ]);
    }

    public function test_operator_lain_tidak_bisa_upload_ke_realisasi_bukan_miliknya(): void
    {
        $operatorA = User::factory()->create();
        $operatorA->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operatorA);

        $operatorB = User::factory()->create();
        $operatorB->assignRole('operator');
        Sanctum::actingAs($operatorB, ['*']);

        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');

        $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ])->assertStatus(403);
    }

    public function test_upload_ditolak_mime_type_tidak_diizinkan(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('script.exe', 500, 'application/x-msdownload');

        $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ])->assertStatus(422);
    }

    public function test_upload_ditolak_ukuran_melebihi_batas(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'); // 10241 KB > 10240 KB

        $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [
            'file' => $file,
        ])->assertStatus(422);
    }

    public function test_upload_ditolak_tanpa_file(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);

        $this->postJson("/api/v1/realizations/{$realization->id}/attachments", [])
            ->assertStatus(422);
    }

    // ── DOWNLOAD ───────────────────────────────────────────────────────

    public function test_owner_dapat_mengunduh_lampirannya_sendiri(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');
        $uploadResponse = $this->postJson("/api/v1/realizations/{$realization->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        $this->get("/api/v1/realizations/{$realization->id}/attachments/{$attachmentId}/download")
            ->assertStatus(200);
    }

    public function test_operator_lain_tidak_bisa_unduh_lampiran_bukan_miliknya(): void
    {
        $operatorA = User::factory()->create();
        $operatorA->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operatorA);

        Sanctum::actingAs($operatorA, ['*']);
        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');
        $uploadResponse = $this->postJson("/api/v1/realizations/{$realization->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        $operatorB = User::factory()->create();
        $operatorB->assignRole('operator');
        Sanctum::actingAs($operatorB, ['*']);

        $this->get("/api/v1/realizations/{$realization->id}/attachments/{$attachmentId}/download")
            ->assertStatus(403);
    }

    public function test_sekretaris_dapat_unduh_lampiran_siapapun(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realization = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');
        $uploadResponse = $this->postJson("/api/v1/realizations/{$realization->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        $this->actingAsSekretaris();

        $this->get("/api/v1/realizations/{$realization->id}/attachments/{$attachmentId}/download")
            ->assertStatus(200);
    }

    public function test_download_404_jika_attachment_bukan_milik_realisasi_ini(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $realizationA = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');
        $uploadResponse = $this->postJson("/api/v1/realizations/{$realizationA->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        $realizationB = $this->createRealizationAsOperator($operator);

        Sanctum::actingAs($operator, ['*']);
        $this->get("/api/v1/realizations/{$realizationB->id}/attachments/{$attachmentId}/download")
            ->assertStatus(404);
    }
}