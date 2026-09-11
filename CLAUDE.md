# CLAUDE.md — SIMONEV Project Memory

> Aturan inti proyek. File ini WAJIB dibaca di awal setiap chat spesialis baru.
> Bukan tempat menyimpan seluruh dokumentasi — hanya keputusan & aturan yang mengikat.

## 1. Project Overview

**SIMONEV** (Sistem Monitoring dan Evaluasi Kinerja) — aplikasi web untuk **1 (satu) Perangkat Daerah**, digunakan untuk memantau target & realisasi kinerja berbasis struktur Renstra/Renja/RKPD (Tujuan → Sasaran → Program → Kegiatan → Sub Kegiatan), termasuk data pendukung (DSSD, Kemiskinan, SPIP, SAKIP, IKU, IKD, Investasi, dll).

Prinsip utama: **FLEKSIBEL, ADAPTIF, MUDAH DIGUNAKAN, MEMPERTAHANKAN HISTORI DATA.**

Baseline desain (Tahap 1–7: Analisis Kebutuhan s.d. Roadmap Pengembangan) **sudah disetujui** — lihat `docs/architecture/README.md`. Jangan mendesain ulang tanpa alasan teknis kuat + persetujuan eksplisit.

## 2. Technology Stack (Approved Baseline)

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 — **PHP 8.3–8.5** (baseline environment: PHP 8.5.2) |
| Frontend | Vue 3 — **Options API only** (bukan Composition API) |
| State management | Pinia |
| HTTP client | Axios |
| Database | MySQL / MariaDB |
| Auth | Laravel Sanctum |
| RBAC | Spatie Laravel-Permission |
| Chart | ApexCharts |
| PDF | DomPDF / mPDF |
| Excel | Laravel Excel (Maatwebsite) |
| Deployment | VPS / On-Premise (Nginx + PHP-FPM) |
| Arsitektur | Structured Modular Monolith, **API-first** |

**Dilarang**: microservices, Kubernetes, distributed architecture, atau teknologi enterprise lain yang tidak diperlukan pada skala 1 Perangkat Daerah, kecuali ada Architecture Decision baru yang disetujui.

## 3. Architecture Principles (wajib diikuti semua chat)

1. **Versioning over overwrite** — perubahan struktur/indikator/target membentuk baris versi baru; data lama tidak pernah dihapus/ditimpa.
2. **Formula as data** — formula capaian disimpan sebagai data terkonfigurasi (tabel `formulas`), tidak hard-coded per indikator.
3. **Relational core** — data kinerja inti (struktur, indikator, target, realisasi) memakai skema relasional ternormalisasi.
4. **Flexible supporting data** — data pendukung (DSSD/SPIP/SAKIP/dll.) memakai skema fleksibel/JSON terkontrol (field_schema per kategori), bukan tabel baru setiap jenis data.
5. **Auditability** — setiap perubahan penting mencatat: siapa, kapan, apa yang diubah, nilai sebelum, nilai sesudah, alasan (jika ada).
6. **API-first** — frontend Vue tidak pernah bergantung langsung pada implementasi backend/database; semua komunikasi lewat REST API terdokumentasi.
7. **Maintainability** — pilih solusi paling sederhana yang cukup; hindari over-engineering untuk skala tim kecil.

## 4. Folder Structure

Lihat `docs/architecture/README.md` §Folder Structure untuk struktur lengkap backend (Laravel) & frontend (Vue).

## 5. Database Principles

- Self-referencing table `performance_structure` (parent_id + level_type) untuk Tujuan–Sasaran–Program–Kegiatan–Sub Kegiatan.
- `indicator_versions` memisahkan konfigurasi (satuan/formula/periode) dari identitas `indicators`, agar revisi tidak merusak histori.
- `targets` & `realizations` selalu tertaut ke `indicator_version_id` / `target_id` tertentu — bukan ke indikator "saat ini".
- Semua tabel transaksi punya `created_by`, `created_at`; perubahan besar (revisi target, perubahan struktur) wajib kolom `reason`.
- Detail ERD lengkap: lihat dokumen desain baseline (Tahap 4).
- **Planning document lineage (CR-002)**: `planning_documents` (self-referencing) menyimpan provenance Renstra→Renja→Renja Perubahan; `targets.planning_document_id` wajib, `performance_structure`/`indicator_versions.planning_document_id` opsional. Lihat §15 log CR-002 untuk detail.
- **Indicator flexibility (CR-003)**: kategori indikator (`indicator_categories`, multi-value via `indicator_category_assignments`) dan arah pengukuran (`measurement_directions`) adalah master data terpisah dari `level_type` struktural. Lihat §15 log CR-003 untuk detail.

## 6. Coding Conventions

- **Backend**: PSR-12, nama tabel `snake_case` plural, nama model `PascalCase` singular, service class untuk business logic (bukan langsung di controller), FormRequest untuk validasi.
- **Frontend**: Vue 3 **Options API** konsisten di seluruh proyek (tidak dicampur Composition API), struktur folder berbasis fitur (feature-based), scoped CSS + CSS variables untuk tema warna instansi, store actions selalu mengembalikan bentuk `{ success, message, data, errors }`.
- **Routing**: route statis/spesifik didahulukan sebelum route dinamis `{id}` untuk menghindari konflik Laravel routing.

## 7. API Conventions

- REST, JSON, prefix `/api/v1/...`.
- Auth: Bearer token via Sanctum.
- Response sukses: `{ success: true, data, message }`; response gagal: `{ success: false, errors, message }`.
- Pagination standar Laravel (`meta`, `links`) untuk list endpoint.
- Setiap endpoint yang mengubah data penting (target/struktur/indikator) mencatat ke `audit_logs`.

## 8. RBAC (Roles)

**Super Admin**, **Admin**, Operator, **Kepala Sub Bidang**, Kepala Bidang, Sekretaris, Kepala Dinas, Pimpinan, Publik — **9 role** (per CR-001, 2026-08-28).

- **Super Admin**: tata kelola platform — manajemen pengguna & role/permission, konfigurasi sistem, master data sistem-kritis, audit tingkat sistem. Tidak otomatis punya akses CRUD data kinerja operasional.
- **Admin**: tata kelola operasional — CRUD struktur & indikator, **eksekutor tunggal penetapan target** (berdasarkan dokumen perencanaan resmi: Renstra/RKPD Perubahan/Renstra Perubahan/Perjanjian Kinerja Perubahan), dapat menjadi **operator backup lintas bidang** (tercatat eksplisit di audit trail sebagai tindakan backup), publikasi ke portal publik.
- **Sekretaris**: mencakup peran Sekretaris Dinas (Sekdin) — **satu role yang sama**, bukan role terpisah. Kewenangan: View/rekap lintas bidang, Review, **Koreksi** (kewenangan proses bisnis — kembalikan untuk perbaikan oleh pihak berwenang sesuai pemilik data/workflow masing-masing → ajukan ulang → review kembali; **bukan** edit langsung nilai realisasi), **Approve/Reject pada tahap rekap** (non-final — approve = diteruskan ke Kadis, reject = dikembalikan ke pihak berwenang). Sekretaris **tidak** memiliki CRUD bebas atas data bidang lain. Untuk data operasional milik unit Sekretariat sendiri, Sekretaris berperan setara Kabid (validasi seperti unit lain).

RBAC matrix lengkap: lihat `docs/architecture/README.md` §2.

**TBD (belum diputuskan, jangan diasumsikan/diimplementasikan)**:
1. Pembagian pengelolaan master data operasional (satuan, periode, unit/bidang) antara Super Admin dan Admin.
2. Apakah Super Admin memiliki akses publikasi/override publik.
3. Apakah Kepala Dinas tetap perlu approval khusus untuk revisi target.
4. Mekanisme pembuatan Super Admin pertama dan berikutnya.

**Catatan organisasi (bukan Architecture Decision)**: Bagian Perencanaan/Perencana sebagai kandidat pemegang role Admin — masih **rencana, belum final**. Sistem tetap dirancang berbasis role, bukan berbasis unit/orang.

## 9. Workflow Validasi (updated — CR-001)

```
Operator (input)
  → Kepala Sub Bidang (Approve/Reject, tingkat sub bidang)
  → Kepala Bidang (Approve/Reject, tingkat bidang)
  → Sekretaris (Review, Koreksi, Approve, Reject — tahap rekap lintas bidang, NON-FINAL)
  → Kepala Dinas (pengesahan final)
```
- Setiap tahap dapat mengembalikan (reject) ke tahap sebelumnya disertai catatan wajib.
- **Approve Sekretaris bukan pengesahan final** — status data menjadi "direkomendasikan/diteruskan ke Kepala Dinas".
- **Koreksi Sekretaris** = kembalikan untuk perbaikan → pihak berwenang (mengikuti pemilik data/workflow masing-masing, tidak dikunci selalu Operator→Kasubbid→Kabid) memperbaiki → ajukan ulang → review kembali. Wajib catatan + audit trail. Tidak pernah mengubah nilai realisasi secara langsung.
- Status "Disahkan" (oleh Kadis) terkunci.
- Penetapan target: Admin (eksekutor tunggal) berdasarkan dokumen resmi, setelah pembahasan bersama Admin + bidang terkait.

## 10. Versioning Rules

- Revisi target/indikator/struktur = INSERT baris baru + tandai versi lama tidak aktif (`is_active=false`, `valid_to` diisi). Tidak pernah `UPDATE` yang menghapus nilai lama.
- Realisasi historis tetap terhubung ke versi target saat realisasi tersebut dicatat (tidak dihitung ulang retroaktif terhadap versi target baru).

## 11. Audit Rules

- Tabel `audit_logs` mencatat seluruh perubahan pada: struktur kinerja, indikator, target, status realisasi, hak akses pengguna.
- Minimal field: `user_id, action, entity_type, entity_id, old_value, new_value, created_at`.

## 12. Testing Rules

- Backend: PHPUnit — minimal feature test per endpoint API (happy path + validation + permission check).
- Frontend: pengujian manual UI checklist per fitur (unit test Vue opsional pada fase lanjutan).
- Setiap fitur baru wajib ada test sebelum ditandai selesai (lihat Definition of Done).

## 13. Development Roadmap (ringkas)

18 fase, lihat `ROADMAP.md` untuk detail tiap fase (tujuan, prerequisite, pekerjaan, output, testing, acceptance criteria, status).

## 14. Current Phase

> **Phase 1 — Project Foundation** — 🟢 SELESAI (disetujui 2026-08-11)
> **Phase 2 — Environment Setup** — 🟢 SELESAI (2026-08-31) — Laravel 13 & Vue 3+Vite terinstal, terverifikasi berjalan, koneksi MariaDB berhasil
> **Phase 3 — Database Design & Migration** — 🟢 SELESAI (2026-09-02) — 29 tabel via 21 migration, rollback/re-migration tervalidasi, seeder 5 tabel master data. ERD final hasil CR-002 + CR-003.
> **Phase 4 — Laravel Backend Foundation** — 🟢 SELESAI (2026-09-03)— ApiResponseTrait, BaseController, exception handler global, pola Service Layer (tanpa Repository), health-check endpoint + Feature Test.
> **Phase 5 — Authentication & RBAC** — 🟢 SELESAI (2026-09-04) — Sanctum + Spatie Laravel-Permission, 9 role/36 permission ter-seed sesuai RBAC Matrix, middleware proteksi route, 10 Feature Test lulus (termasuk constraint Sekretaris non-final).
> **Phase 6 — Master Data** — 🟢 SELESAI (2026-09-10) — CRUD satuan/formula/periode pelaporan/unit-bidang, model `Unit` baru, delete guard & deactivate/activate guard, 30 Feature Test baru (40 total lulus). Wording ROADMAP "Admin only" diselaraskan dengan RBAC Matrix final (CR-001). TBD-1 tetap terbuka.
> **Phase 7 — Performance Structure** — 🟡 SEBAGIAN SELESAI: Phase 7A (Structure Core) selesai 2026-09-11; Phase 7B (Revision Workflow) belum dimulai, menunggu resolusi Design Gap

## 15. Important Decisions Log

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-08-10 | Baseline desain Tahap 1–7 disetujui sebagai baseline proyek | Persetujuan Master Project Chat |
| 2026-08-10 | Tambah role **Kepala Sub Bidang** ke RBAC & workflow validasi | Menyesuaikan struktur organisasi riil |
| 2026-08-10 | Stack tetap: Laravel 12 + Vue 3 Options API + MySQL, modular monolith, API-first | Sesuai skala 1 Perangkat Daerah, hindari over-engineering |
| 2026-08-11 | Seluruh dokumen foundation (CLAUDE.md, ROADMAP.md, README.md, CHANGELOG.md, docs/architecture/README.md) + Dokumen Desain Tahap 1–7 ditetapkan sebagai **baseline resmi final** proyek | Persetujuan eksplisit pemilik proyek |
| 2026-08-11 | Phase 1 — Project Foundation ditandai **selesai**; Phase 2 — Environment Setup berikutnya | Acceptance criteria Phase 1 terpenuhi |
| 2026-08-11 | Pemilik proyek menyatakan penerimaan final Phase 1 dan meminta proses **Change Management** eksplisit ditetapkan agar proyek tetap adaptif terhadap perubahan spesifikasi/fitur | Menjaga fleksibilitas proyek tanpa mengorbankan disiplin roadmap — lihat §17 |
| 2026-08-21 | **Architecture Decision — Backend framework: Laravel 12 → Laravel 13.** Environment: PHP 8.5.2. Alasan: Laravel 13 resmi mendukung PHP 8.3–8.5 (Laravel 12 hanya resmi 8.2–8.4 dan bug-fix-nya sudah berakhir 13 Agu 2026); Laravel 13 adalah release terbaru dengan bug-fix s.d. Q3 2027 & security fix s.d. Q1 2028; environment lokal developer sudah PHP 8.5.2. Dampak: perubahan versi framework murni — **tidak mengubah** business requirement, ERD, business process, UI/UX, roadmap, maupun architecture principles (Structured Modular Monolith, API-first, versioning-over-overwrite, formula-as-data, relational core + flexible JSON, full auditability tetap berlaku sepenuhnya). | Disetujui eksplisit pemilik proyek, via Change Management Process §17 |
| 2026-08-28 | **Architecture Decision (CR-001, AD-1) — Tambah role Super Admin**, terpisah dari Admin. Super Admin: tata kelola platform (user/role/permission, konfigurasi sistem, master data sistem-kritis, audit sistem). Admin: tata kelola operasional (struktur, indikator, target, backup operator). Role bertambah dari 8 → 9. | Menghindari bottleneck/single point of failure bila seluruh kewenangan administrasi hanya di 1 role |
| 2026-08-28 | **Architecture Decision (CR-001, AD-2) — Penetapan target disentralisasi ke Admin** (eksekutor tunggal), berdasarkan dokumen perencanaan resmi (Renstra/RKPD Perubahan/Renstra Perubahan/Perjanjian Kinerja Perubahan), setelah pembahasan bersama bidang terkait. Kabid & Sekretaris berpartisipasi dalam pembahasan, tidak lagi mengeksekusi penetapan target. | Menjaga konsistensi data, keamanan, dan kepatuhan pada dokumen perencanaan resmi |
| 2026-08-28 | **Architecture Decision (CR-001, AD-3) — Perluasan kewenangan Sekretaris** menjadi Review, Koreksi (kewenangan proses bisnis, mekanisme kembalikan→perbaiki→ajukan ulang, bukan CRUD langsung), Approve, Reject pada tahap rekap lintas bidang — bersifat **non-final** (approve = diteruskan ke Kadis; Kadis tetap pengesah final). Sekretaris = Sekretaris Dinas (Sekdin), **satu role yang sama**, bukan role terpisah. Workflow validasi: Kasubbid → Kabid → Sekretaris (rekap) → Kadis. | Mengakomodasi kebutuhan review/koreksi organisasi tanpa menghapus kendali Kabid atas bidangnya maupun kewenangan pengesahan final Kadis |
| 2026-08-28 | **Architecture Decision (CR-001, AD-4) — Notifikasi MVP dibatasi kanal email** (Laravel Notification, database + SMTP). WhatsApp/API gateway dicatat sebagai future enhancement, arsitektur tetap channel-agnostic (tidak perlu redesign untuk menambah kanal nanti). | Pertimbangan biaya & keterbatasan API gratis pada tahap awal; baseline notifikasi sudah cukup fleksibel |
| 2026-08-28 | 4 hal terkait CR-001 ditetapkan **TBD** (belum diputuskan, jangan diimplementasikan): pembagian master data operasional Super Admin vs Admin; akses publikasi/override Super Admin; kewajiban approval Kadis atas revisi target; mekanisme pembuatan Super Admin pertama & berikutnya. Status "Bagian Perencanaan/Perencana sebagai kandidat Admin" dicatat sebagai rencana organisasi, **bukan** Architecture Decision. | Menghindari asumsi/implementasi prematur atas hal yang belum disepakati pemilik proyek |
| 2026-08-31 | **Phase 2 — Environment Setup dinyatakan selesai.** Laravel 13 (v13.29.0) terinstal di `backend/`, Vue 3 + Vite (+ Vue Router, Pinia, Axios) terinstal di `frontend/`, koneksi ke MariaDB (`simonev_db`) terverifikasi, `php artisan serve` & `npm run dev` berjalan tanpa error. `backend/.env.example` diperbaiki (sebelumnya default sqlite, sekarang mysql sesuai baseline) dan `README.md` dilengkapi agar proses clone-and-run dapat diikuti sesuai Acceptance Criteria. Sempat dipertimbangkan Supabase/PostgreSQL sebagai alternatif database — ditolak, tetap MariaDB/VPS on-premise sesuai baseline. | Acceptance Criteria Phase 2 terpenuhi; persetujuan eksplisit pemilik proyek |
| 2026-09-01 | **Architecture Decision (CR-002) — Planning Document Lineage, Option D "Lineage Tagging".** Tambah tabel `planning_documents` (self-referencing `parent_document_id`, lifecycle status penuh: draft/submitted/under_review/approved/active/superseded/rejected) untuk merepresentasikan hierarki Renstra→Renja→Renja Perubahan. Tambah kolom `planning_document_id`: nullable di `performance_structure` & `indicator_versions` (metadata provenance, tidak mengendalikan versioning), NOT NULL di `targets` (menegakkan CR-001 AD-2 di level database — target wajib bertaut dokumen resmi). Mekanisme versioning existing (`is_active`, `parent_id`, `revision_no`) **tidak berubah** — tetap satu-satunya penentu status berlaku. | Baseline Tahap 4 belum eksplisit menjawab "target/struktur ini berasal dari dokumen perencanaan resmi yang mana" — dibutuhkan untuk audit trail dan kepatuhan terhadap CR-001 AD-2. Opsi B (FK penuh planning_documents→performance_structure sebagai pengendali versi) ditolak karena menciptakan coupling ketat yang bertentangan dengan fleksibilitas performance_structure yang sudah ada. |
| 2026-09-01 | **Architecture Decision (CR-003) — Fleksibilitas & Kategori Indikator.** Tambah `indicator_categories` (master, extensible — seeder awal: IKU, IKD/IKK) + `indicator_category_assignments` (junction dengan audit fields, mendukung satu indikator memiliki lebih dari satu kategori sekaligus). Tambah `measurement_directions` (master data, bukan ENUM — Naik Lebih Baik/Turun Lebih Baik/Netral) + `indicator_versions.direction_id`. Tambah kolom deskriptif di `indicator_versions`: `operational_definition`, `measurement_method`, `data_source`. **Ditegaskan**: "Indikator Tujuan/Sasaran/Program/Kegiatan/Subkegiatan" BUKAN kategori — diturunkan dari `indicators.structure_id → performance_structure.level_type`, tidak disimpan sebagai baris `indicator_categories`. | Kebutuhan mengakomodasi indikator lintas dokumen (Renstra/Renja/IKU/Perjanjian Kinerja) dan definisi operasional per indikator, tanpa duplikasi konsep dengan `level_type` yang sudah ada dan tanpa membuat tabel terpisah per jenis indikator. |
| 2026-09-02 | **Phase 3 — Database Design & Migration dinyatakan selesai.** 29 tabel (21 migration baru + 1 alter `users`) terimplementasi sesuai ERD final (baseline Tahap 4 + CR-002 + CR-003). Rollback penuh (21 migration, 2 tahap eksekusi) dan re-migration tervalidasi tanpa error SQL/FK. Seeder 5 tabel master data terverifikasi idempotent (2x `db:seed`, tidak ada duplikat). `roles`/`user_roles` tetap ditunda ke Phase 5 sesuai baseline §8. | Acceptance Criteria `ROADMAP.md` Phase 3 terpenuhi; persetujuan eksplisit pemilik proyek |
| 2026-09-03 | **Architecture Decision — Service Layer tanpa Repository pattern.** Pola backend ditetapkan 2 lapis: Controller → Service → Eloquent Model langsung, bukan 3 lapis dengan Repository interface terpisah (pola umum C#/.NET). Didemonstrasikan via `HealthCheckService`. | Eloquent sudah merupakan lapisan abstraksi data (Active Record + Query Builder); Repository dianggap duplikasi abstraksi untuk skala 1 Perangkat Daerah, tim kecil, tanpa rencana ganti database engine (Supabase/PostgreSQL sudah ditolak eksplisit); sesuai §3 prinsip Maintainability. Repository dapat diajukan ulang sebagai Architecture Decision terpisah bila kebutuhan nyata muncul di masa depan. |
| 2026-09-03 | **Phase 4 — Laravel Backend Foundation dinyatakan selesai.** `ApiResponseTrait` + `BaseController` menegakkan format response `{success,data,message}`/`{success,errors,message}` (§7). Exception handler global di `bootstrap/app.php` menangani ValidationException/AuthenticationException/AuthorizationException/ModelNotFoundException/Throwable generik untuk rute `/api/*`. `routes/api.php` didaftarkan manual (tanpa `php artisan install:api`) agar Sanctum tidak terinstal prematur sebelum Phase 5. Endpoint `GET /api/v1/health` + 2 Feature Test (`HealthTest`, 2 passed) sebagai bukti verifikasi. | Acceptance Criteria `ROADMAP.md` Phase 4 terpenuhi; persetujuan eksplisit pemilik proyek |
| 2026-09-04 | **Phase 5 — Authentication & RBAC dinyatakan selesai.** Laravel Sanctum (token API) dan Spatie Laravel-Permission (RBAC) terinstal dan dimigrasi. 9 role + 36 permission (32 assignable, 4 TBD CR-001 sengaja dibuat tanpa di-assign) ter-seed penuh sesuai RBAC Matrix `docs/architecture/README.md` §2.4. Middleware `permission:`/`role:` terdaftar dan dibuktikan bekerja end-to-end via endpoint internal `/api/v1/test-permission`. Constraint bisnis kritis (Approve Sekretaris BUKAN status final) diverifikasi otomatis: Sekretaris tidak memiliki `realization.finalize.kadis`, hanya `kepala_dinas` yang memilikinya. Audit eksplisit terhadap `$fillable` `unit_id` pada model `User` — diputuskan tidak ditambahkan, ditunda ke Service User Management (CR-001 TBD-4). | Acceptance Criteria `ROADMAP.md` Phase 5 terpenuhi; persetujuan eksplisit pemilik proyek |
| 2026-09-08 | **Design Decision — Sinkronisasi wording ROADMAP.md Phase 6 dengan RBAC Matrix final (bukan Architecture Decision baru, murni klarifikasi dokumentasi).** Ditemukan konflik antara wording awal `ROADMAP.md` Phase 6 ("Admin only", "Admin dapat CRUD seluruh master data") dengan RBAC Matrix final hasil CR-001 (klasifikasi master data kritis vs operasional, `formulas` = kritis/Super-Admin-manage, `units_of_measure`/`reporting_periods`/`units` = operasional/TBD-1). Diputuskan: **Opsi A** — RBAC Matrix final tetap sebagai sumber kebenaran authorization; wording ROADMAP lama diakui sebagai baseline awal yang belum tersinkronisasi (bukan keputusan yang mengikat), diselaraskan pada tahap closure Phase 6 tanpa mengubah sejarah keputusan yang sudah tercatat. `master-data-operasional.manage` **tidak** di-assign ke role manapun untuk "memaksakan" wording lama — TBD-1 tetap terbuka. | Mencegah penyelesaian TBD-1 CR-001 secara diam-diam melalui implementasi; menjaga RBAC Matrix Phase 5 sebagai satu-satunya sumber kebenaran otorisasi yang sudah diverifikasi & di-test |
| 2026-09-10 | **Phase 6 — Master Data dinyatakan selesai.** CRUD penuh untuk `units_of_measure`, `formulas`, `reporting_periods` (physical delete dengan Service-layer guard terhadap referensi `indicator_versions`, HTTP 409 terstruktur, `restrictOnDelete()` DB sebagai lapisan terakhir) dan `units` (model `Unit` baru — belum ada sejak Phase 3 — dengan deactivate/activate menggantikan physical delete, guard user aktif & child aktif, guard circular hierarchy). Permission mengikuti RBAC Matrix final tanpa perubahan assignment: `formulas` → `master-data-kritis.manage`/`.view` (Super Admin manage, Admin view-only, sesuai Phase 5); `units_of_measure`/`reporting_periods`/`units` → `master-data-operasional.manage` (**tetap unassigned**, TBD-1 CR-001 tidak diselesaikan). Audit logging untuk master data **tidak diimplementasikan** (di luar cakupan eksplisit §11 — struktur kinerja/indikator/target/status realisasi/hak akses pengguna); dievaluasi sebagai kandidat Change Request terpisah bila diperlukan. `periods_per_year` divalidasi `max:255` sebagai *technical safety bound* (kapasitas kolom `unsignedTinyInteger`), bukan business rule final — tetap dapat direvisi via CR jika dibutuhkan batas bisnis spesifik. 30 Feature Test baru + regresi penuh 40 test/78 assertion lulus tanpa kegagalan, tanpa perubahan migration Phase 3. | Acceptance Criteria `ROADMAP.md` Phase 6 (versi disinkronkan) terpenuhi; persetujuan eksplisit pemilik proyek |
| 2026-09-11 | Phase 7 dipecah menjadi Phase 7A (Structure Core) dan Phase 7B (Revision Workflow). Phase 7A diimplementasikan tanpa mekanisme revisi/approval karena schema `performance_structure` belum punya kolom status lifecycle maupun linkage antar-versi. `structure.propose-revision` tetap ada di RBAC namun belum dipakai; `structure.approve-revision` tetap TBD-3/CR-001, tidak di-assign. `structure.view.own-subunit` (Kepala Sub Bidang) belum diberi akses endpoint performance-structure karena tidak ada `unit_id` di tabel ini — dicatat sebagai Design Gap, bukan diasumsikan/dikarang. | Keputusan owner via proses INSPECT→ANALYZE→DESIGN→REVIEW→APPROVE, menghindari implementasi lifecycle revisi yang ambigu di atas schema tidak lengkap |

## 16. Known Limitations (tahap ini)

- Belum ada integrasi otomatis ke SIPD/e-Planning (disiapkan sebagai fase lanjutan).
- Belum ada SSO lintas OPD (Sanctum cukup untuk 1 aplikasi/1 OPD).
- Belum ada aplikasi mobile native.

## 17. Change Management Process (spesifikasi & fitur)

Sistem dan proses proyek ini sengaja dirancang agar **tahan terhadap perubahan spesifikasi** — baik perubahan Renstra/Renja/RKPD di level data, maupun perubahan kebutuhan fitur di level proyek.

**Level sistem (sudah built-in lewat prinsip arsitektur §3)**:
- Struktur, indikator, target → versioning-over-overwrite → perubahan tidak pernah merusak histori.
- Formula capaian → formula-as-data → formula baru ditambahkan tanpa deploy ulang.
- Data pendukung → skema fleksibel/JSON per kategori → kategori data baru tidak butuh migration baru.
- Role & permission → RBAC granular via Spatie → role/permission baru ditambahkan tanpa restrukturisasi kode.

**Level proyek (proses, berlaku untuk semua chat spesialis)** — mengikuti alur berikut sebelum perubahan apa pun terhadap baseline disepakati:
1. Jelaskan keputusan sebelumnya yang relevan.
2. Jelaskan perubahan yang diminta.
3. Analisis dampak: database, backend, frontend, roadmap.
4. Identifikasi risiko.
5. Berikan rekomendasi (terima/tolak/modifikasi).
6. **Tunggu persetujuan eksplisit pemilik proyek** — jangan mengubah kode/dokumen sebelum disetujui.
7. Jika disetujui: dokumentasikan sebagai **Architecture Decision** baru di §15, tandai fase `ROADMAP.md` yang terdampak, dan catat di `CHANGELOG.md`.

Perubahan kecil (copy, styling, non-struktural) tidak memerlukan proses penuh ini — cukup dicatat di `CHANGELOG.md` pada fase berjalan.

---
*Update file ini setiap ada keputusan arsitektur penting. Jangan biarkan file ini kadaluarsa.*