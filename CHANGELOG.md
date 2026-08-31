# CHANGELOG.md — SIMONEV

Format mengacu pada prinsip [Keep a Changelog](https://keepachangelog.com/) yang disederhanakan untuk kebutuhan internal proyek. Setiap keputusan arsitektur besar dicatat di sini **dan** di `CLAUDE.md` §15 (Important Decisions Log).

## [2026-08-31] — Phase 2: Instalasi Laravel 13 & Konfigurasi Database (STEP 2.2)

### Added
- `.gitignore` root untuk backend (Laravel) dan frontend (Vue) — mencegah
  `vendor/`, `node_modules/`, dan `.env` ter-track oleh Git.
- Laravel 13 (v13.29.0) terinstal di `backend/` via `composer create-project`.
- Konfigurasi `.env` backend diarahkan ke MariaDB lokal (`simonev_db`) via XAMPP.

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
- Tidak ada credential (`.env`) ter-track oleh Git (diverifikasi via
  `git check-ignore`).

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