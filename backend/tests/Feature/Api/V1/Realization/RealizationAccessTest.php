<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\Realization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationAccessTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    private const STATUSES = [
        'draft',
        'diajukan',
        'divalidasi_kasubbid',
        'divalidasi_kabid',
        'direkap_sekretaris',
        'dikembalikan',
        'disahkan',
    ];

    private const QUEUE = '/api/v1/realizations/approval-queue';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function makeRealization(User $owner, string $status): Realization
    {
        return Realization::factory()->ownedBy($owner)->create([
            'target_id'         => $this->createActiveTargetWithFormula()->id,
            'status'            => $status,
            'realization_value' => 80,
            'achievement_pct'   => 80,
            'deviation'         => -20,
        ]);
    }

    /** [role, tahap yang menjadi tanggung jawabnya] */
    public static function antreanPerRole(): array
    {
        return [
            'kasubbid'   => ['kepala_sub_bidang', 'diajukan'],
            'kabid'      => ['kepala_bidang', 'divalidasi_kasubbid'],
            'sekretaris' => ['sekretaris', 'divalidasi_kabid'],
            'kadis'      => ['kepala_dinas', 'direkap_sekretaris'],
        ];
    }

    /** [role yang dibatasi tahap, tahapnya] — Sekretaris/Kadis sudah punya akses baca luas di Phase 10. */
    public static function tahapDetail(): array
    {
        return [
            'kasubbid' => ['kepala_sub_bidang', 'diajukan'],
            'kabid'    => ['kepala_bidang', 'divalidasi_kasubbid'],
        ];
    }

    // ── ANTREAN ──────────────────────────────────────────────────────────

    #[DataProvider('antreanPerRole')]
    public function test_antrean_hanya_berisi_realisasi_pada_tahap_aktor(string $role, string $stage): void
    {
        $ownerA = $this->createUserWithRole('operator');
        $ownerB = $this->createUserWithRole('operator');

        foreach (self::STATUSES as $status) {
            if ($status !== $stage) {
                $this->makeRealization($ownerA, $status);
            }
        }

        // Dua pemilik berbeda pada tahap yang sama: filter unit belum tersedia (temporary limitation).
        $expected = [
            $this->makeRealization($ownerA, $stage)->id,
            $this->makeRealization($ownerB, $stage)->id,
        ];
        sort($expected);

        Sanctum::actingAs($this->createUserWithRole($role), ['*']);

        $response = $this->getJson(self::QUEUE)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame($expected, $ids);
        $this->assertSame(2, $response->json('meta.total'));

        foreach ($response->json('data') as $row) {
            $this->assertSame($stage, $row['status']);
        }
    }

    public function test_antrean_ditolak_403_untuk_role_tanpa_permission_tahap(): void
    {
        foreach (['operator', 'admin', 'pimpinan'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role), ['*']);

            $this->getJson(self::QUEUE)->assertStatus(403);
        }
    }

    public function test_antrean_tanpa_autentikasi_ditolak_401(): void
    {
        $this->getJson(self::QUEUE)->assertStatus(401);
    }

    public function test_antrean_berpaginasi_dengan_meta_dan_links(): void
    {
        $owner = $this->createUserWithRole('operator');

        for ($i = 0; $i < 3; $i++) {
            $this->makeRealization($owner, 'diajukan');
        }

        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        $page1 = $this->getJson(self::QUEUE.'?per_page=2')->assertStatus(200);
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(3, $page1->json('meta.total'));
        $this->assertSame(2, $page1->json('meta.per_page'));
        $this->assertSame(2, $page1->json('meta.last_page'));
        $this->assertSame(1, $page1->json('meta.current_page'));
        $this->assertNotNull($page1->json('links.next'));
        $this->assertNull($page1->json('links.prev'));

        $page2 = $this->getJson(self::QUEUE.'?per_page=2&page=2')->assertStatus(200);
        $this->assertCount(1, $page2->json('data'));
        $this->assertSame(2, $page2->json('meta.current_page'));
        $this->assertNull($page2->json('links.next'));
        $this->assertNotNull($page2->json('links.prev'));
    }

    public function test_per_page_tidak_valid_ditolak_422_dan_kosong_memakai_default(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        foreach (['0', '101', 'abc'] as $value) {
            $this->getJson(self::QUEUE.'?per_page='.$value)->assertStatus(422);
        }

        $this->getJson(self::QUEUE.'?per_page=')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_antrean_diurutkan_dari_yang_paling_lama_menunggu(): void
    {
        $owner = $this->createUserWithRole('operator');
        $a = $this->makeRealization($owner, 'diajukan');
        $b = $this->makeRealization($owner, 'diajukan');
        $c = $this->makeRealization($owner, 'diajukan');

        DB::table('realizations')->where('id', $a->id)->update(['updated_at' => now()->subDays(1)]);
        DB::table('realizations')->where('id', $b->id)->update(['updated_at' => now()->subDays(3)]);
        DB::table('realizations')->where('id', $c->id)->update(['updated_at' => now()->subDays(2)]);

        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        $ids = collect($this->getJson(self::QUEUE)->json('data'))->pluck('id')->all();

        $this->assertSame([$b->id, $c->id, $a->id], $ids);
    }

    public function test_item_antrean_mempertahankan_input_by_sebagai_id_dan_memuat_target(): void
    {
        $owner = $this->createUserWithRole('operator');
        $this->makeRealization($owner, 'diajukan');

        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        $response = $this->getJson(self::QUEUE)->assertStatus(200);
        $row = $response->json('data.0');

        $this->assertSame($owner->id, $row['input_by']); // FK tidak tertimpa relasi
        $this->assertIsArray($row['target']);
        $this->assertArrayHasKey('target_value', $row['target']);
        $this->assertFalse($row['is_final']);
        $this->assertSame('Diajukan', $row['status_label']);
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page', 'from', 'to'], array_keys($response->json('meta')));
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($response->json('links')));
    }

    public function test_jumlah_query_antrean_tidak_bertambah_seiring_jumlah_data(): void
    {
        $owner = $this->createUserWithRole('operator');
        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        for ($i = 0; $i < 3; $i++) {
            $this->makeRealization($owner, 'diajukan');
        }

        $queryCount = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::QUEUE.'?per_page=50')->assertStatus(200);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $queryCount(); // pemanasan: cache permission dan relasi user
        $withThree = $queryCount();

        for ($i = 0; $i < 9; $i++) {
            $this->makeRealization($owner, 'diajukan');
        }

        $withTwelve = $queryCount();

        $this->assertSame($withThree, $withTwelve);
    }

    // ── DETAIL (show) ────────────────────────────────────────────────────

    #[DataProvider('tahapDetail')]
    public function test_kasubbid_dan_kabid_hanya_dapat_membuka_detail_pada_tahapnya(string $role, string $stage): void
    {
        $owner = $this->createUserWithRole('operator');
        Sanctum::actingAs($this->createUserWithRole($role), ['*']);

        foreach (self::STATUSES as $status) {
            $realization = $this->makeRealization($owner, $status);

            $this->getJson("/api/v1/realizations/{$realization->id}")
                ->assertStatus($status === $stage ? 200 : 403);
        }
    }

    public function test_approver_tetap_dapat_membuka_realisasi_yang_pernah_diprosesnya(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $kasubbidLain = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');

        Sanctum::actingAs($kasubbid, ['*']);
        $this->postJson("/api/v1/realizations/{$realization->id}/approve")->assertStatus(200);

        // Status kini di tahap Kabid; Kasubbid yang memvalidasi tetap bisa melihat.
        $this->getJson("/api/v1/realizations/{$realization->id}")->assertStatus(200);
        $this->getJson("/api/v1/realizations/{$realization->id}/history")->assertStatus(200);

        // Kasubbid lain tidak pernah bertindak dan bukan pada tahapnya.
        Sanctum::actingAs($kasubbidLain, ['*']);
        $this->getJson("/api/v1/realizations/{$realization->id}")->assertStatus(403);
        $this->getJson("/api/v1/realizations/{$realization->id}/history")->assertStatus(403);
    }

    public function test_sekretaris_dan_kadis_tetap_dapat_membuka_detail_pada_status_apa_pun(): void
    {
        $owner = $this->createUserWithRole('operator');

        foreach (['sekretaris', 'kepala_dinas'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role), ['*']);

            foreach (['draft', 'disahkan'] as $status) {
                $realization = $this->makeRealization($owner, $status);

                $this->getJson("/api/v1/realizations/{$realization->id}")->assertStatus(200);
            }
        }
    }

    // ── LAMPIRAN (download) ──────────────────────────────────────────────

    public function test_kasubbid_dan_kabid_dapat_mengunduh_lampiran_hanya_pada_tahapnya(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $kabid = $this->createUserWithRole('kepala_bidang');
        $realization = $this->makeRealization($owner, 'draft');

        Sanctum::actingAs($owner, ['*']);
        $upload = $this->postJson(
            "/api/v1/realizations/{$realization->id}/attachments",
            ['file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]
        )->assertStatus(201);

        $url = "/api/v1/realizations/{$realization->id}/attachments/{$upload->json('data.id')}/download";

        $realization->update(['status' => 'diajukan']);

        Sanctum::actingAs($kasubbid, ['*']);
        $this->get($url)->assertStatus(200);

        Sanctum::actingAs($kabid, ['*']);
        $this->get($url)->assertStatus(403);

        $realization->update(['status' => 'divalidasi_kasubbid']);

        Sanctum::actingAs($kabid, ['*']);
        $this->get($url)->assertStatus(200);

        Sanctum::actingAs($kasubbid, ['*']);
        $this->get($url)->assertStatus(403);
    }

    // ── RIWAYAT (history) ────────────────────────────────────────────────

    public function test_history_menampilkan_riwayat_berurutan_dengan_nama_aktor(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'draft');

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/v1/realizations/{$realization->id}/submit")->assertStatus(200);

        Sanctum::actingAs($kasubbid, ['*']);
        $this->postJson("/api/v1/realizations/{$realization->id}/approve")->assertStatus(200);

        Sanctum::actingAs($owner, ['*']);
        $rows = $this->getJson("/api/v1/realizations/{$realization->id}/history")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $rows);
        $this->assertSame(['submit', 'validate'], array_column($rows, 'action'));
        $this->assertSame(['diajukan', 'divalidasi_kasubbid'], array_column($rows, 'to_status'));
        $this->assertSame(
            [$owner->name, $kasubbid->name],
            array_map(fn (array $row): string => $row['actor']['name'], $rows)
        );
    }

    public function test_history_realisasi_tidak_ditemukan_menghasilkan_404(): void
    {
        Sanctum::actingAs($this->createUserWithRole('sekretaris'), ['*']);

        $this->getJson('/api/v1/realizations/999999/history')->assertStatus(404);
    }
}