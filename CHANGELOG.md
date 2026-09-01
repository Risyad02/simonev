# CHANGELOG.md — SIMONEV

Format mengacu pada prinsip [Keep a Changelog](https://keepachangelog.com/) yang disederhanakan untuk kebutuhan internal proyek. Setiap keputusan arsitektur besar dicatat di sini **dan** di `CLAUDE.md` §15 (Important Decisions Log).

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