# CHANGELOG.md — SIMONEV

Format mengacu pada prinsip [Keep a Changelog](https://keepachangelog.com/) yang disederhanakan untuk kebutuhan internal proyek. Setiap keputusan arsitektur besar dicatat di sini **dan** di `CLAUDE.md` §15 (Important Decisions Log).

## [2026-09-10] — Phase 6: Master Data — Implementation

### Added
- CRUD lengkap untuk 4 entitas master data sesuai scope `ROADMAP.md` Phase 6: **Satuan** (`units_of_measure`), **Formula** (`formulas`), **Periode Pelaporan** (`reporting_periods`), **Unit/Bidang** (`units`).
- Model baru `App\Models\Unit` (belum ada sejak Phase 3) dengan relasi `parent()`, `children()`, `users()`.
- Pola `Controller → FormRequest → Service → Model/Query Builder` diterapkan konsisten untuk keempat entitas, tanpa Repository, mengikuti baseline Service Layer.
- Delete guard eksplisit di Service layer untuk `units_of_measure`, `formulas`, `reporting_periods` — memeriksa referensi ke `indicator_versions` sebelum delete, mengembalikan `409 Conflict` terstruktur via `ApiResponseTrait` (bukan mengandalkan `QueryException` mentah dari `restrictOnDelete()` di database, yang tetap dipertahankan sebagai lapisan proteksi terakhir).
- Mekanisme deactivate/activate untuk `units` (`PATCH /units/{id}/deactivate`, `PATCH /units/{id}/activate`) menggantikan hard delete — **tidak ada endpoint `DELETE` fisik untuk `units`**, sesuai prinsip §3.1 (Versioning over Overwrite) dan keputusan governance eksplisit bahwa unit adalah bagian struktur organisasi yang harus mempertahankan histori.
- Guard deactivate `units`: menolak (409) jika unit memiliki user aktif (`users.is_active=true`) atau child unit aktif. Guard activate: menolak (409) jika parent unit sedang nonaktif.
- Guard circular hierarchy pada update `parent_unit_id` (unit tidak boleh menjadi leluhur dirinya sendiri).
- 30 Feature Test baru (semua lulus, 60 assertions gabungan across 4 file test) mencakup: happy path CRUD, validasi (required/unique/format), authorization per permission boundary, delete-guard reference check, dan deactivate/activate guard.

### RBAC Boundary (mengikuti baseline final Phase 5 — tidak ada perubahan assignment)
- `formulas` diklasifikasikan sebagai **master data kritis** (§2.2 `docs/architecture/README.md`) — endpoint `GET` memakai `master-data-kritis.view|master-data-kritis.manage`; endpoint mutasi (`POST/PUT/PATCH/DELETE`) memakai `master-data-kritis.manage` (Super Admin-only, sesuai assignment Phase 5).
- `units_of_measure`, `reporting_periods`, `units` diklasifikasikan sebagai **master data operasional** (§2.4 RBAC Matrix) — seluruh endpoint memakai `master-data-operasional.manage`.
- **`master-data-operasional.manage` TETAP TIDAK di-assign ke role manapun** — TBD-1 CR-001 tetap terbuka, tidak diselesaikan secara diam-diam melalui implementasi Phase 6 ini. Endpoint tersedia dan terproteksi middleware, tetapi tidak dapat diakses oleh role manapun sampai TBD-1 diputuskan secara formal.

### Documentation Sync — Wording ROADMAP.md Diselaraskan
- Wording lama `ROADMAP.md` Phase 6 ("Modul master data berfungsi penuh (**Admin only**)"; "**Admin dapat CRUD seluruh master data**") ditulis sebelum RBAC Matrix final (CR-001) memperkenalkan klasifikasi master data kritis vs operasional secara terpisah — wording tersebut adalah baseline awal yang belum tersinkronisasi, bukan keputusan authorization yang mengikat.
- Acceptance Criteria Phase 6 disinkronkan mengikuti RBAC Matrix final sebagai sumber kebenaran authorization: keberhasilan Phase 6 diukur dari ketersediaan CRUD sesuai scope, penerapan middleware permission yang benar, dan authorization backend yang berfungsi (role dengan permission dapat akses, role tanpa permission ditolak) — bukan dari literalitas "Admin dapat CRUD seluruh master data".
- Sejarah keputusan tidak diubah: baseline awal tetap tercatat menyebut "Admin only"; perubahan ini adalah penyelarasan dokumentasi terhadap RBAC final, bukan revisi retroaktif.

### Known Open Items (tidak diselesaikan pada Phase 6, sesuai batas scope yang disepakati)
- `periods_per_year` validasi `max:255` diterapkan sebagai **technical safety bound** (kapasitas kolom `unsignedTinyInteger`), **bukan** keputusan business rule final — status tetap TBD menunggu keputusan pemilik proyek jika diperlukan batas bisnis yang lebih spesifik.
- Audit logging untuk CRUD master data (termasuk activate/deactivate `units`) **tidak diimplementasikan** — dievaluasi tidak diwajibkan oleh cakupan `audit_logs` pada `CLAUDE.md` §11 (struktur kinerja/indikator/target/status realisasi/hak akses pengguna), namun secara teknis struktur `audit_logs` mampu menampungnya bila di masa depan diperlukan melalui Change Request terpisah.
- Proteksi khusus deactivate root unit (`parent_unit_id IS NULL`) **tidak diimplementasikan** — tidak ada dasar baseline untuk aturan ini; keputusan eksplisit pemilik proyek untuk tidak menambahkannya pada Phase 6.

## [2026-09-04] — Phase 5: SELESAI — Authentication & RBAC

Penutup Phase 5 — Authentication & RBAC. Menyiapkan fondasi autentikasi (Laravel Sanctum) dan otorisasi (Spatie Laravel-Permission) untuk 9 role sesuai CR-001, dengan constraint bisnis kritis (Approve Sekretaris non-final) terverifikasi otomatis via Feature Test — sebagai fondasi middleware proteksi route untuk seluruh endpoint bisnis Phase 6 dan seterusnya.

### Added
- Laravel Sanctum (`^4.3`) — autentikasi token-based API. `personal_access_tokens` table (migration `2026_09_03_084438`).
- Spatie Laravel-Permission (`^8.3`) — RBAC. 5 tabel (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) via migration `2026_09_03_132148`.
- `app/Models/User.php` — trait `HasApiTokens` (Sanctum) dan `HasRoles` (Spatie) ditambahkan.
- `app/Http/Requests/Auth/LoginRequest.php` — validasi `email`/`password`, pesan Bahasa Indonesia.
- `app/Http/Controllers/Api/V1/AuthController.php` — 3 endpoint: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`. Login memakai `Hash::check()` manual + `createToken()` (pola stateless API, bukan `Auth::attempt()` berbasis session).
- `database/seeders/RoleSeeder.php` — 9 role sesuai CR-001 (`super_admin`, `admin`, `operator`, `kepala_sub_bidang`, `kepala_bidang`, `sekretaris`, `kepala_dinas`, `pimpinan`, `publik`), `guard_name: 'web'` (diverifikasi cocok dengan `config/auth.php` `defaults.guard`).
- `database/seeders/PermissionSeeder.php` — 36 permission, dipetakan 1:1 terhadap seluruh baris RBAC Matrix `docs/architecture/README.md` §2.4: 32 permission assignable + **4 permission TBD CR-001 (dibuat, sengaja tidak di-assign ke role manapun)**: `master-data-operasional.manage` (TBD-1), `structure.approve-revision` & `target.approve-revision` (TBD-3), `public-portal.override` (TBD-2).
- `database/seeders/RolePermissionSeeder.php` — assignment permission ke 9 role sesuai matrix, dengan komentar eksplisit menandai 4 permission TBD yang sengaja tidak di-assign.
- `bootstrap/app.php` — alias middleware Spatie (`role`, `permission`, `role_or_permission`) terdaftar di `withMiddleware()`.
- `app/Http/Controllers/Api/V1/TestPermissionController.php` + route `GET /api/v1/test-permission` (`middleware: auth:sanctum, permission:user.manage`) — endpoint internal, bukan bagian API bisnis, murni untuk membuktikan integrasi Sanctum + Spatie + middleware bekerja end-to-end.
- `tests/Feature/Api/V1/PermissionMiddlewareTest.php` — 6 Feature Test: akses diterima (permission ada), akses ditolak (permission tidak ada, 403), akses ditolak (belum autentikasi, 401), seeder role/permission dapat dipakai middleware, **dan 2 test constraint bisnis kritis**: Sekretaris tidak punya `realization.finalize.kadis`, dan permission tersebut hanya dimiliki `kepala_dinas`.
- `tests/TestCase.php` — trait `RefreshDatabase` ditambahkan (sebelumnya kosong), diperlukan agar Feature Test RBAC punya database bersih per test run (SQLite in-memory, sesuai `phpunit.xml`).

### Architecture Decisions Referenced
- **Permission granularity per-scope** (bukan permission generik) — dashboard (5 varian: `full`/`operational`/`own-scope`/`cross-unit`/`strategic-summary`) dan realization (4 varian: `manage`/`manage.backup`/`view`/`view.cross-unit`) dipisah eksplisit agar RBAC matrix lebih auditable, sesuai kewenangan berbeda per role di `docs/architecture/README.md` §2.4.
- **4 permission TBD CR-001: dibuat, tidak di-assign** — bukan ditunda pembuatannya, bukan pula ditebak assignment-nya. Permission exist di database (siap dipakai) tapi sengaja tidak terhubung ke role manapun sampai keputusan CR-001 resmi turun — mengurangi effort saat TBD dijawab (cukup edit `RolePermissionSeeder`, tanpa migration/seeder permission baru).
- **`guard_name: 'web'` untuk seluruh role/permission**, meski autentikasi API memakai `auth:sanctum` — karena Spatie menentukan guard berdasarkan `config('auth.defaults.guard')` (`'web'`), bukan guard aktif saat request; instance `User` yang dikembalikan Sanctum tetap sama, sehingga `hasRole()`/`can()` tetap bekerja benar. Diverifikasi terhadap `config/auth.php` sebelum seeder dijalankan.
- **`unit_id` TIDAK ditambahkan ke `$fillable` model `User`** — hasil audit eksplisit (INSPECT → ANALYZE → DECIDE). `unit_id` adalah parameter otorisasi (menentukan scope akses data), bukan field profil netral; mass assignment umum berisiko membuka celah self-reassignment unit tanpa kontrol. Assignment akan dilakukan via property langsung (`$user->unit_id = ...; $user->save();`) di dalam Service User Management pada fase mendatang, terkait CR-001 TBD-4 (mekanisme pembuatan Super Admin pertama) yang masih terbuka.

### Verified
- Login/logout/me diverifikasi manual via Postman: login → token diterbitkan; `/me` dengan token valid → 200; logout → token revoked; `/me` dengan token yang sudah logout → `401 Tidak terautentikasi`.
- Data seeder diverifikasi via Tinker: `Role::count()` = 9, `Permission::count()` = 36, keempat permission TBD `roles()->count()` = 0 untuk semua, `sekretaris->permissions` (9 item, tanpa `realization.finalize.kadis`), `realization.finalize.kadis->roles` = `['kepala_dinas']` saja.
- Feature Test: `php artisan test` → **10 passed (24 assertions)** — mencakup `HealthTest`/`ExampleTest` existing (tanpa regresi setelah `RefreshDatabase` ditambahkan) + 6 test RBAC baru.
- Migration Phase 3 (21 file) terbukti kompatibel penuh dengan SQLite in-memory (testing environment) tanpa modifikasi apapun.

### Incidents (ditemukan & diperbaiki selama proses)
1. Docblock method `casts()` di `User.php` sempat terhapus tidak sengaja saat edit manual (kemungkinan efek auto-format editor) — ditemukan saat review `git diff` di Langkah 6 audit `unit_id`, dikembalikan sebelum closure.
2. Re-indentasi otomatis pada blok `withExceptions()` di `bootstrap/app.php` (dari 8-spasi tidak konsisten menjadi 4-spasi konsisten) — efek samping editor saat menyunting `withMiddleware()` di file yang sama. Dinilai perbaikan positif (memperbaiki inkonsistensi sejak Phase 4), dibiarkan, diverifikasi tidak mengubah perilaku (10 test tetap lulus).

### Phase 5 — Acceptance Criteria (ROADMAP.md)
| Kriteria | Status |
|---|---|
| Semua 9 role dapat login | ✅ Diverifikasi manual (login endpoint universal, tidak bergantung role) |
| Akses hanya sesuai matrix RBAC (`docs/architecture/README.md` §2.4) | ✅ Middleware `permission:` diverifikasi end-to-end via `/test-permission` + Feature Test |
| Approve Sekretaris tidak menghasilkan status final | ✅ **Terverifikasi eksplisit** — Sekretaris tidak memiliki `realization.finalize.kadis`, permission tersebut hanya milik `kepala_dinas` |

### Not Yet Implemented (tetap sesuai batas scope Phase 5)
- Endpoint bisnis (target, realisasi, struktur kinerja, indikator, publikasi) — tetap Phase 6 dan seterusnya sesuai roadmap; middleware `permission:` baru diterapkan di endpoint dummy testing (`/test-permission`), bukan endpoint bisnis nyata.
- Service/Policy User Management (assignment `unit_id`, pembuatan Super Admin pertama) — menunggu keputusan CR-001 TBD-4.
- 4 permission TBD CR-001 tetap unassigned — menunggu keputusan resmi.

### Impacted Files
`backend/app/Models/User.php`, `backend/bootstrap/app.php`, `backend/routes/api.php`, `backend/tests/TestCase.php`, `backend/composer.json`, `backend/composer.lock` (modified); `backend/app/Http/Controllers/Api/V1/AuthController.php`, `backend/app/Http/Controllers/Api/V1/TestPermissionController.php`, `backend/app/Http/Requests/Auth/LoginRequest.php`, `backend/config/sanctum.php`, `backend/config/permission.php`, `backend/database/migrations/2026_09_03_084438_create_personal_access_tokens_table.php`, `backend/database/migrations/2026_09_03_132148_create_permission_tables.php`, `backend/database/seeders/RoleSeeder.php`, `backend/database/seeders/PermissionSeeder.php`, `backend/database/seeders/RolePermissionSeeder.php`, `backend/database/seeders/DatabaseSeeder.php` (modified), `backend/tests/Feature/Api/V1/PermissionMiddlewareTest.php` (baru); branch `feature/phase5-auth-rbac`.

## [2026-09-03] — Phase 4: SELESAI — Laravel Backend Foundation

Penutup Phase 4 — Laravel Backend Foundation. Menyiapkan fondasi struktur backend (response helper, base controller, exception handler global, pola service layer) sebagai kerangka yang akan diikuti seluruh fase CRUD berikutnya (Phase 6–16), memenuhi Acceptance Criteria `ROADMAP.md` Phase 4.

### Added
- `app/Http/Traits/ApiResponseTrait.php` — helper `success()`/`error()`, menegakkan format response standar `CLAUDE.md` §7: `{ success: true, data, message }` untuk sukses, `{ success: false, errors, message }` untuk gagal.
- `app/Http/Controllers/Api/V1/BaseController.php` — abstract base controller, `use ApiResponseTrait`; seluruh controller resource ke depan extends class ini.
- `app/Http/Controllers/Api/V1/HealthController.php` — endpoint health-check pertama (`GET /api/v1/health`), sebagai bukti verifikasi format response Phase 4.
- `app/Services/HealthCheckService.php` — contoh/referensi pola Service Layer (Controller → Service → Eloquent Model langsung, **tanpa Repository terpisah** — keputusan disepakati eksplisit, lihat Architecture Decisions Referenced). Tidak menyentuh database (belum ada domain data nyata di Phase 4); murni referensi struktur untuk Phase 6 dst.
- `routes/api.php` — didaftarkan manual di `bootstrap/app.php` (`api:` + `apiPrefix: 'api/v1'`), **tanpa** menjalankan `php artisan install:api` agar Laravel Sanctum tidak ikut terinstal prematur (tetap murni scope Phase 5).
- Exception handler global di `bootstrap/app.php` (`->withExceptions()`) — menangani `ValidationException` (422), `AuthenticationException` (401), `AuthorizationException` (403), `ModelNotFoundException`/`NotFoundHttpException` (404), dan `Throwable` generik (500, detail pesan disembunyikan bila `APP_DEBUG=false`) — seluruhnya mengembalikan format `{ success: false, errors, message }` konsisten untuk rute `/api/*`.
- `tests/Feature/Api/V1/HealthTest.php` — 2 Feature Test: happy path (`GET /api/v1/health` → 200, struktur response sesuai kontrak) dan error path (rute API tidak dikenal → 404, membuktikan exception handler global bekerja).

### Architecture Decisions Referenced
- **Service Layer tanpa Repository pattern** — disepakati eksplisit sebagai pola 2 lapis (Controller → Service → Eloquent Model), bukan 3 lapis dengan Repository interface terpisah (seperti pola umum di C#/.NET). Alasan: Eloquent sudah merupakan lapisan abstraksi data (Active Record + Query Builder); menambah Repository dianggap duplikasi abstraksi untuk skala proyek ini (1 Perangkat Daerah, tim kecil, tidak ada rencana ganti database engine — Supabase/PostgreSQL sudah ditolak eksplisit); sesuai `CLAUDE.md` §3 prinsip Maintainability. Repository dapat diajukan sebagai Architecture Decision terpisah di masa depan bila kebutuhan nyata muncul.
- **`routes/api.php` didaftarkan manual**, bukan via `php artisan install:api` — keputusan teknis eksplisit untuk mencegah Sanctum terinstal sebelum Phase 5, menjaga urutan fase roadmap (`CLAUDE.md` §12).

### Verified
- `GET /api/v1/health` → `200 OK`, `Content-Type: application/json`, body `{"success":true,"data":{"status":"ok","timestamp":"...","checked_by":"HealthCheckService"},"message":"API SIMONEV berjalan normal"}` — diverifikasi manual via `curl`.
- `GET /api/v1/<rute-tidak-ada>` → `404 Not Found`, body `{"success":false,"errors":null,"message":"Data tidak ditemukan"}` — membuktikan exception handler global aktif untuk rute API (bukan halaman HTML default Laravel).
- Feature Test: `php artisan test --filter=HealthTest` → **2 passed (13 assertions)**.

### Phase 4 — Acceptance Criteria (ROADMAP.md)
| Kriteria | Status |
|---|---|
| Format response API konsisten sesuai `CLAUDE.md` §7 | ✅ |
| Endpoint health-check berjalan dengan format response standar | ✅ |
| Struktur folder `app/Services`, `app/Http/Requests` disiapkan | ✅ `app/Services` (dengan contoh) — `app/Http/Requests` konvensi disepakati, folder fisik ditunda ke Phase 6 (belum ada domain data nyata) |

### Not Yet Implemented (tetap sesuai batas scope Phase 4)
- `app/Http/Requests/` (folder fisik + FormRequest per domain) — menyusul Phase 6 saat ada domain data nyata untuk divalidasi.
- `app/Policies/` — prasyarat Spatie Laravel-Permission, Phase 5.
- `app/Notifications/` — scope Phase 16.
- Laravel Sanctum — tetap murni Phase 5, sengaja tidak terinstal di Phase 4 ini.

### Impacted Files
`backend/bootstrap/app.php`, `backend/routes/api.php` (baru), `backend/app/Http/Traits/ApiResponseTrait.php` (baru), `backend/app/Http/Controllers/Api/V1/BaseController.php` (baru), `backend/app/Http/Controllers/Api/V1/HealthController.php` (baru), `backend/app/Services/HealthCheckService.php` (baru), `backend/tests/Feature/Api/V1/HealthTest.php` (baru), branch `feature/phase4-backend-foundation`.

## [2026-09-02] — Phase 3: SELESAI — Rollback/Re-Migration Validation & Master Data Seeder

Penutup Phase 3 — Database Design & Migration. Melengkapi entri migration Kelompok A-F sebelumnya dengan validasi rollback penuh dan seeder master data, memenuhi seluruh Acceptance Criteria `ROADMAP.md` Phase 3.

### Added
- Seeder master data untuk 5 tabel lookup: `units_of_measure` (8 satuan, FR-03), `formulas` (6 formula type, `expression` sengaja NULL — ditunda ke Phase 4 formula engine, FR-04), `reporting_periods` (4 periode: Bulanan/Triwulanan/Semesteran/Tahunan), `measurement_directions` (3 arah, CR-003.8), `indicator_categories` (IKU, IKD/IKK — extensible, CR-003.9).
- 5 model Eloquent minimal (`UnitOfMeasure`, `Formula`, `ReportingPeriod`, `MeasurementDirection`, `IndicatorCategory`) sebagai prasyarat teknis seeder — hanya `$fillable`, tanpa business logic/relasi/service (di luar cakupan Phase 3).
- `DatabaseSeeder.php` diperbarui: scaffold default (`User::factory()`) dihapus, memanggil kelima seeder di atas.

### Verified
- **Rollback validation**: seluruh 21 migration Phase 3 di-rollback penuh (`migrate:rollback --step=21`, dieksekusi 2 tahap karena kesalahan awal menyamakan step dengan batch — dikoreksi via `migrate:status` sebelum lanjut) tanpa error SQL/FK, tabel terverifikasi hilang total dari database (`SHOW TABLES` → hanya 9 tabel default Laravel tersisa).
- **Re-migration validation**: seluruh 21 migration dijalankan ulang dari kondisi kosong tanpa error, 29 tabel kembali lengkap.
- **Seeder idempotency**: `php artisan db:seed` dijalankan 2 kali berurutan (`updateOrCreate()` dengan `name` sebagai natural key aplikatif), jumlah baris identik di kedua run (8/6/4/3/2) — tidak ada duplikat.
- Seluruh proses (rollback, re-migration, seeder) tidak mengubah file migration maupun schema — working tree tetap clean di setiap checkpoint.

### Incidents (ditemukan & diperbaiki selama proses)
1. Kesalahan perhitungan `--step` vs jumlah batch pada rollback pertama — terdeteksi via `migrate:status`, dikoreksi dengan menghitung ulang sisa migration dari output nyata.
2. Model `UnitOfMeasure` sempat menebak nama tabel `unit_of_measures` (konvensi Eloquent default: pluralisasi kata terakhir) padahal tabel aktual `units_of_measure` — diperbaiki dengan `protected $table` eksplisit.

### Phase 3 — Acceptance Criteria (ROADMAP.md)
| Kriteria | Status |
|---|---|
| Skema database berjalan via `php artisan migrate` | ✅ 29 tabel |
| Seeder dasar | ✅ 5 tabel master data, idempotent |
| Migration & rollback berjalan tanpa error | ✅ Full rollback + re-migration tervalidasi |
| Constraint FK konsisten | ✅ |
| Skema sesuai ERD baseline, direview oleh Database Chat | ✅ ERD final (CR-002 + CR-003) |

### Not Yet Implemented (tetap sesuai batas scope Phase 3)
- `roles`/`user_roles` — ditunda ke Phase 5 (Spatie Laravel-Permission).
- `formulas.expression` (formula engine) — Phase 4.
- Data transaksional (`units`, `users`, `planning_documents`, struktur/indikator/target nyata) — bukan bagian seeder Phase 3.

### Impacted Files
`backend/database/seeders/` (5 file baru + `DatabaseSeeder.php` modified), `backend/app/Models/` (5 file baru), branch `feature/phase3-database-migration`.

## [2026-09-01] — Phase 3: Database Migration — Kelompok D, E, F (Konfigurasi Indikator, Realisasi & Validasi, Data Pendukung) — MIGRATION PHASE 3 SELESAI

Penutup seluruh migration inti Phase 3. Bagian dari implementasi ERD final hasil CR-002 (Planning Document Lineage) dan CR-003 (Fleksibilitas & Kategori Indikator), dengan enum status/action final disepakati sebelum implementasi Kelompok E dimulai.

### Added — Kelompok D (Konfigurasi Indikator & Target)
- Migration `indicator_categories` — master kategori indikator (IKU, IKD/IKK, extensible), sesuai CR-003.6.
- Migration `indicator_versions` — versi konfigurasi indikator lengkap, mencakup: FK ke `units_of_measure`/`formulas`/`reporting_periods` (baseline), `direction_id` nullable ke `measurement_directions` (CR-003.8), `planning_document_id` nullable (CR-002.4), serta kolom deskriptif `operational_definition`/`measurement_method`/`data_source` (CR-003 §4).
- Migration `indicator_category_assignments` — junction table dengan audit fields (`assigned_by`, `assigned_at`, `is_active`, `valid_to`), mendukung satu indikator memiliki lebih dari satu kategori sekaligus (CR-003.6), dengan lineage dokumen opsional.
- Migration `targets` — target per `indicator_version`, dengan `planning_document_id` **NOT NULL** + `restrictOnDelete()` (CR-002.5), menegakkan CR-001 §B (target hanya ditetapkan berdasarkan dokumen resmi) di level database.

### Added — Kelompok E (Realisasi & Validasi Berjenjang)
- Migration `realizations` — realisasi per target, `status` (string, default `draft`) mengikuti 7 nilai lifecycle final: `draft, diajukan, divalidasi_kasubbid, divalidasi_kabid, direkap_sekretaris, disahkan, dikembalikan`.
- Migration `realization_attachments` — bukti dukung realisasi, `realization_id` dengan `restrictOnDelete()` (preservasi histori, bukan cascade).
- Migration `approval_history` — jejak audit transisi status, dengan `action` (`submit | validate | approve | reject | koreksi | recap`) sengaja dipisah dari `from_status`/`to_status` — menegaskan bahwa "Koreksi" adalah tindakan proses bisnis (CR-001 §C), bukan status lifecycle tersendiri.

### Added — Kelompok F (Data Pendukung, Publikasi, Audit, Notifikasi)
- Migration `supporting_data_categories`, `supporting_data_entries` — modul data pendukung fleksibel (DSSD/SPIP/SAKIP/dll.) dengan skema JSON (`field_schema`, `payload`), sesuai prinsip flexible JSON baseline.
- Migration `publications` — penanda publikasi ke portal publik, relasi polimorfik (`entity_type`+`entity_id`) tanpa FK database, sesuai baseline Tahap 4 dokumen desain asli.
- Migration `audit_logs` — jejak audit menyeluruh, append-only (tanpa `updated_at`), polimorfik tanpa FK, `user_id` nullable (log tidak boleh hilang meski user terkait sudah dihapus).
- Migration `notifications` — notifikasi in-app per user, `user_id` dengan **`restrictOnDelete()`** (direvisi dari draf awal `cascadeOnDelete()` melalui review checkpoint — dipertahankan konsisten dengan prinsip non-destructive di seluruh 20+ FK Phase 3, karena `users.is_active` sudah menjadi mekanisme standar penghapusan non-destruktif).

### Architecture Decisions Referenced
- **CR-002** (Planning Document Lineage) — selesai diimplementasikan penuh: `planning_documents` + kolom provenance di `performance_structure`, `indicator_versions`, dan `targets` (wajib).
- **CR-003** (Fleksibilitas Indikator) — selesai diimplementasikan penuh: kategori multi-value, direction sebagai master data, definisi operasional di level versi.
- Enum status `realizations` (7 nilai) dan struktur `approval_history` (`action` terpisah dari `status`) dikunci sebagai baseline Phase 3, disetujui eksplisit sebelum migration Kelompok E dibuat.

### Verified
- Seluruh 12 tabel baru terverifikasi via `php artisan migrate:status`, `SHOW TABLES`, `DESCRIBE`, dan `SHOW INDEX` — dicocokkan manual terhadap isi file migration.
- 1 insiden ditemukan dan diperbaiki: migration `create_notifications_table` sempat tidak mencerminkan revisi `restrictOnDelete()` yang telah disetujui pada checkpoint review (masih `cascadeOnDelete()` di draf awal). Ditemukan sebelum `php artisan migrate` dijalankan, diperbaiki di file sebelum eksekusi — tidak berdampak pada skema database.
- **Migration inti Phase 3 selesai: 29 tabel total** (28 tabel domain SIMONEV + `migrations`), sesuai ERD final yang disetujui melalui CR-002 + CR-003.

### Not Yet Implemented (menyusul setelah migration Phase 3)
- Seeder master data awal (`units_of_measure`, `formulas`, `reporting_periods`, `measurement_directions`, `indicator_categories`, dan struktur organisasi awal).
- `roles`/`user_roles` — tetap ditunda ke Phase 5 (Spatie Laravel-Permission), sesuai keputusan awal proyek.

### Impacted Files
`backend/database/migrations/` (12 file baru), branch `feature/phase3-database-migration`.

## [2026-09-01] — Phase 3: Database Migration — Kelompok A, B, C (Fondasi Organisasi, Master Data, Struktur Kinerja)

Bagian dari implementasi ERD final Phase 3, hasil Change Management CR-002 (Planning Document Lineage) dan CR-003 (Fleksibilitas & Kategori Indikator). Proses persetujuan penuh (Analyze → Reconcile → Impact Assessment → Decision Matrix → Approval) telah dilakukan sebelum implementasi dimulai.

### Added
- Migration `units` — struktur organisasi self-referencing (`parent_unit_id`), mendukung hierarki Bidang→Sub Bidang dan level tambahan di masa depan.
- Migration alter `users` — kolom `unit_id` (nullable, disiapkan untuk RBAC Phase 5) dan `is_active`, sesuai kolom kunci baseline Dokumen Desain §4.3.
- Migration `units_of_measure`, `formulas`, `reporting_periods` — master data baseline (satuan, formula-as-data, periode pelaporan).
- Migration `measurement_directions` — master data baru (CR-003.8), merepresentasikan arah pengukuran (naik/turun lebih baik) sebagai data terkonfigurasi, bukan ENUM terkunci.
- Migration `planning_documents` — tabel baru (CR-002), self-referencing (`parent_document_id`) untuk lineage Renstra→Renja→Renja Perubahan, dengan lifecycle status penuh (`draft, submitted, under_review, approved, active, superseded, rejected`).
- Migration `performance_structure` — struktur kinerja inti self-referencing (`parent_id`, `level_type`), dengan kolom tambahan `planning_document_id` (nullable, provenance metadata sesuai CR-002) dan `created_by` (audit, sesuai prinsip CR-001 §5).
- Migration `indicators` — identitas indikator, tertaut wajib ke `performance_structure` via `structure_id` dengan `restrictOnDelete()` (mencegah penghapusan struktur yang masih memiliki indikator, konsisten prinsip versioning-over-overwrite — bukan hard delete).

### Architecture Decisions Referenced
- **CR-002** (Planning Document Lineage) — Option D "Lineage Tagging": `planning_document_id` sebagai metadata provenance, bukan pengendali versioning. Mekanisme existing (`is_active`, `parent_id`, `revision_no`) tetap satu-satunya penentu status berlaku.
- **CR-003** (Fleksibilitas Indikator) — kategori indikator (IKU/IKD/dll.) dan direction akan diimplementasikan sebagai tabel terpisah pada kelompok migration berikutnya (`indicator_categories`, `indicator_category_assignments`, kolom tambahan `indicator_versions`).

### Verified
- Seluruh 7 tabel baru + 1 alter tabel terverifikasi via `php artisan migrate:status`, `SHOW TABLES`, `DESCRIBE`, dan `SHOW INDEX` — dicocokkan manual terhadap isi file migration, bukan diasumsikan dari status Laravel semata.
- 1 insiden ditemukan dan diperbaiki: migration `add_unit_id_to_users_table` sempat tereksekusi dari isi file kosong/belum tersimpan (tercatat "Ran" di tabel `migrations` tanpa perubahan skema nyata). Diperbaiki dengan menghapus record migration yang salah dari tabel `migrations`, lalu re-run `php artisan migrate` dengan isi file yang benar. Skema akhir terverifikasi sesuai rancangan.

### Not Yet Implemented (menyusul di kelompok migration berikutnya)
- `indicator_categories`, `indicator_category_assignments`, `indicator_versions` (termasuk kolom `direction_id`, `operational_definition`, `measurement_method`, `data_source`, `planning_document_id`), `targets`, `realizations`, `realization_attachments`, `approval_history`, dan tabel pendukung lain sesuai Migration Plan yang disetujui.

### Impacted Files
`backend/database/migrations/` (7 file baru), branch `feature/phase3-database-migration`.

## [2026-08-31] — Phase 2: Environment Setup Backend & Frontend (STEP 2.2 & 2.3)

### Added
- `.gitignore` root untuk backend (Laravel) dan frontend (Vue) — mencegah
  `vendor/`, `node_modules/`, dan `.env` ter-track oleh Git.
- Laravel 13 (v13.29.0) terinstal di `backend/` via `composer create-project`.
- Konfigurasi `.env` backend diarahkan ke MariaDB lokal (`simonev_db`) via XAMPP.
- Vue 3 + Vite terinstal di `frontend/` via `npm create vue@latest`.
- Dependency frontend: `vue-router@4`, `pinia`, `axios` ditambahkan sesuai
  baseline stack (tidak ter-include otomatis saat scaffolding karena prompt
  interaktif CLI tidak tertangkap penuh di Git Bash/MINGW64).
- Wiring dasar frontend: `main.js` (registrasi Pinia & Router), `App.vue`
  (root `router-view`), `router/index.js` (1 route awal), `views/HomeView.vue`
  (halaman placeholder verifikasi).

### Changed
- Composer diupdate dari v2.8.12 ke v2.10.3 untuk menutup 8 celah keamanan
  (termasuk CVE-2026-24739 yang relevan untuk lingkungan Git Bash/Windows)
  dan memperbarui pubkey verifikasi tag/dev (diverifikasi cocok dengan
  sumber resmi composer.github.io/pubkeys.html).

### Removed
- File bootstrap Laravel Boost (`backend/AGENTS.md`, `backend/CLAUDE.md`)
  bawaan skeleton `laravel/laravel`, dihapus untuk menghindari konflik
  otoritas dokumentasi dengan `CLAUDE.md` root proyek. Tidak ada dependency
  Laravel Boost yang diinstal — file hanya template bootstrap, tidak dieksekusi.

### Verified
- Koneksi Laravel → MariaDB (`simonev_db`) berhasil melalui `php artisan migrate`.
- `php artisan serve` berjalan normal, endpoint dasar (`http://127.0.0.1:8000`)
  dapat diakses tanpa error.
- `npm run dev` berjalan normal, aplikasi Vue dapat diakses di
  `http://localhost:5173` tanpa error.
- Tidak ada credential (`.env`) ter-track oleh Git (diverifikasi via
  `git check-ignore`), demikian pula `frontend/node_modules`.

### Considered but Rejected
- Sempat dipertimbangkan penggunaan Supabase/PostgreSQL sebagai alternatif
  database. Ditolak — tetap pada baseline MySQL/MariaDB, VPS/On-Premise.
  Tidak diajukan sebagai Change Request karena tidak jadi ada deviasi
  aktual dari baseline.
  
## [2026-08-28] — CR-001: Perubahan Hak Akses, Workflow, dan Struktur Admin

Diproses melalui Change Management Process (`CLAUDE.md` §17): Analyze → Clarify → Proposed Decision → **Approval** → Update Documentation (tahap ini) → Verify → Implement (belum dimulai).

### Added
- Role baru **Super Admin**, terpisah dari **Admin** (AD-1). Total role RBAC: 8 → **9**.
- Kewenangan Sekretaris diperluas: View/Rekap lintas bidang, Review, **Koreksi** (kewenangan proses bisnis — kembalikan untuk perbaikan oleh pihak berwenang sesuai pemilik data/workflow masing-masing → ajukan ulang → review kembali), **Approve/Reject pada tahap rekap** (non-final) (AD-3).
- Ditegaskan: **Sekretaris = Sekretaris Dinas (Sekdin)** — satu role yang sama, tidak ada role terpisah.

### Changed
- Penetapan target **disentralisasi ke Admin** (eksekutor tunggal), berdasarkan dokumen perencanaan resmi, setelah pembahasan bersama bidang terkait (AD-2). Kabid & Sekretaris kini "ikut pembahasan", tidak lagi mengeksekusi.
- Workflow validasi: Kasubbid → Kabid → **Sekretaris (Review/Koreksi/Approve/Reject, non-final)** → Kadis (final).
- Notifikasi MVP ditegaskan dibatasi kanal **email**; WhatsApp/API gateway dicatat sebagai future enhancement (AD-4).
- RBAC Matrix di `docs/architecture/README.md` §2.4 direvisi total mengikuti struktur role baru.

### Marked as TBD (belum diputuskan, jangan diimplementasikan)
1. Pembagian pengelolaan master data operasional antara Super Admin dan Admin.
2. Akses publikasi/override portal publik oleh Super Admin.
3. Kewajiban approval Kepala Dinas atas revisi target.
4. Mekanisme pembuatan Super Admin pertama dan berikutnya.

### Noted (bukan Architecture Decision)
- Status "Bagian Perencanaan/Perencana sebagai kandidat pemegang role Admin" dicatat sebagai rencana organisasi, **belum final**. Sistem tetap dirancang berbasis role.

### Impacted Files
`CLAUDE.md` (§8, §9, §15), `ROADMAP.md` (Phase 5, 9, 10, 11), `README.md` (Struktur Peran), `docs/architecture/README.md` (§1, §2 — restrukturisasi penuh), Dokumen Desain SIMONEV (Tahap 2.2/2.5, Tahap 3.3).

### Also Included in Commit `d05849e` (outside CR-001 scope)
- `GEMINI.md` (root) and `docs/ai/AI_WORKFLOW.md` — governance/instruction files for running Gemini CLI alongside Claude on this repo. Reviewed 2026-08-29: no credentials/secrets present; content is consistent with (not contradictory to) `CLAUDE.md` as supreme source of truth, Options API-only rule, Git branching/approval rules, and STOP-on-TBD behavior already established in this project. Not part of CR-001 itself — noted here for commit-history accuracy only.

## [2026-08-21] — Architecture Decision: Backend Framework Laravel 12 → Laravel 13

### Changed
- **Backend framework diganti dari Laravel 12 ke Laravel 13**, menyesuaikan environment developer (PHP 8.5.2) yang berada di rentang dukungan resmi Laravel 13 (PHP 8.3–8.5), bukan Laravel 12 (resmi PHP 8.2–8.4, bug-fix berakhir 13 Agustus 2026).
- Diputuskan melalui Change Management Process (`CLAUDE.md` §17): analisis dampak dilakukan sebelum perubahan, disetujui eksplisit oleh pemilik proyek.

### Not Changed (dikonfirmasi tetap final)
- Seluruh architecture principles, RBAC, role Kepala Sub Bidang, workflow validasi, ERD/desain database, business process, UI/UX, dan roadmap 18 fase — **tidak berubah**. Ini murni penyesuaian versi framework backend.

### Verification Required (sebelum implementasi Phase 2 lanjut)
- Kompatibilitas Laravel Sanctum, Spatie Laravel-Permission, DomPDF/mPDF, Laravel Excel, dan PHPUnit terhadap Laravel 13 diverifikasi nyata via Composer (lihat hasil di bawah), bukan diasumsikan.

### Dependency Compatibility Result (verifikasi nyata, 2026-08-21)
Composer dependency resolution langsung diblokir jaringan sandbox kerja Claude (repo.packagist.org tidak ada di allowlist, HTTP 403) — bukan diasumsikan gagal, ini hasil percobaan nyata. Sebagai gantinya, kompatibilitas setiap paket diverifikasi langsung dari data rilis Packagist.org (bukan asumsi):

| Paket | Versi terbaru | Requires (relevan) | Kompatibel Laravel 13 / PHP 8.5 |
|---|---|---|---|
| laravel/sanctum | v4.3.3 | illuminate/* `^11.0\|^12.0\|^13.0`, php `^8.2` | ✅ Ya |
| spatie/laravel-permission | 8.3.0 | illuminate/* `^12.0\|^13.0`, php `^8.3` | ✅ Ya |
| barryvdh/laravel-dompdf | v3.1.2 | illuminate/support `^9\|^10\|^11\|^12\|^13.0`, php `^8.1` | ✅ Ya |
| maatwebsite/excel | 3.1.69 (juga ada 4.0.1) | illuminate/support s.d. `^13.0`, php `^7.0\|^8.0` (3.1.x) / `^8.3` (4.0.x) | ✅ Ya |
| phpunit/phpunit | 12.x / 13.x | PHPUnit 12 & 13 keduanya aktif mendukung PHP 8.3–8.5 | ✅ Ya |

**Kesimpulan**: seluruh dependency inti yang akan dipakai SIMONEV sudah merilis versi yang eksplisit mendukung `laravel/framework ^13.0` dan PHP 8.5, per Agustus 2026. Tidak ada blocker.

## [2026-08-11] — Phase 1 Completed (Final Acceptance)

### Confirmed
- Pemilik proyek secara resmi menyatakan **Phase 1 — Project Foundation diterima dan selesai (Completed)**.
- Status Phase 1 pada `ROADMAP.md` dan `CLAUDE.md` dikonfirmasi 🟢 **Completed**.

### Added
- Bagian baru `CLAUDE.md` §17 — **Change Management Process**: menjelaskan bagaimana proyek menampung perubahan spesifikasi/fitur baik di level sistem (versioning-over-overwrite, formula-as-data, skema fleksibel data pendukung, RBAC granular) maupun di level proses (analisis dampak wajib → persetujuan eksplisit → dokumentasi sebagai Architecture Decision) sebelum baseline diubah.

### Next
- Phase 2 — Environment Setup **belum dimulai** — menunggu persetujuan eksplisit pemilik proyek untuk memulai.

## [2026-08-11] — Phase 1 Approved & Closed

### Approved
- Pemilik proyek menyetujui seluruh dokumen foundation (`CLAUDE.md`, `ROADMAP.md`, `README.md`, `CHANGELOG.md`, `docs/architecture/README.md`) beserta Dokumen Desain Tahap 1–7 sebagai **baseline resmi final**.
- Seluruh keputusan arsitektur, roadmap, struktur role (termasuk Kepala Sub Bidang), prinsip versioning, formula engine, RBAC, workflow validasi, dan teknologi dinyatakan **final** kecuali ada permintaan perubahan eksplisit disertai analisis dampak.

### Changed
- Status Phase 1 — Project Foundation: 🟡 Berjalan → 🟢 Selesai.
- Phase 2 — Environment Setup ditetapkan sebagai fase berikutnya (belum dimulai, menunggu instruksi lanjut).

## [Unreleased] — Phase 1: Project Foundation

### Added
- Dokumen desain baseline disetujui (Tahap 1–7: Analisis Kebutuhan, Business Process, Desain Sistem, Desain Database, Rekomendasi Teknologi, UI/UX, Roadmap Pengembangan).
- Role **Kepala Sub Bidang** ditambahkan ke RBAC dan alur validasi berjenjang (di antara Operator dan Kepala Bidang).
- Struktur proyek awal: `README.md`, `ROADMAP.md`, `CLAUDE.md`, `CHANGELOG.md`, `docs/architecture/README.md`.
- Aturan Git, standar API/database/coding/testing/dokumentasi, dan Definition of Done ditetapkan.
- Roadmap 18 fase pengembangan ditetapkan sebagai fixed roadmap.

### Decided
- Baseline teknologi dikonfirmasi: Laravel 12, Vue 3 (Options API), Pinia, MySQL/MariaDB, Sanctum, Spatie Permission, ApexCharts, DomPDF/mPDF, Laravel Excel, VPS/On-Premise, Structured Modular Monolith, API-first.
- Model pengembangan lintas chat spesialis (Master, Backend, Frontend, Database, UI/UX, QA/Testing, Deployment) ditetapkan.

### Not Started
- Implementasi kode (Phase 2 dan seterusnya) belum dimulai — menunggu persetujuan pemilik proyek atas dokumen foundation.