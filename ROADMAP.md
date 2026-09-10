# ROADMAP.md — SIMONEV Development Roadmap

> Urutan pengembangan ini **fixed** — tidak boleh diubah sembarangan. Perubahan urutan harus didokumentasikan sebagai Architecture Decision di `CLAUDE.md`.
> Jangan melompat ke fase berikutnya sebelum fase sebelumnya memenuhi Acceptance Criteria & Definition of Done (lihat `docs/architecture/README.md` §Definition of Done).

Status legend: ⚪ Belum mulai · 🟡 Berjalan · 🟢 Selesai/Completed

> Perubahan spesifikasi atau fitur di tengah jalan tetap dapat diakomodasi tanpa mengubah urutan roadmap — lihat proses **Change Management** di `CLAUDE.md` §17. Perubahan yang disetujui akan menandai fase terkait di sini (kolom Status) tanpa memindahkan urutannya.

---

## Phase 1 — Project Foundation — 🟢 COMPLETED
- **Tujuan**: Menetapkan struktur proyek, dokumentasi inti, standar kerja, dan Definition of Done sebelum implementasi dimulai.
- **Prerequisite**: Dokumen desain baseline (Tahap 1–7) disetujui.
- **Pekerjaan**: Struktur folder, README/ROADMAP/CLAUDE/CHANGELOG, aturan Git, standar API/DB/coding/testing/dokumentasi, proses Change Management.
- **Output**: Dokumen proyek awal (file ini beserta README.md, CLAUDE.md, CHANGELOG.md, docs/architecture/README.md).
- **Testing**: Review manual dokumen oleh pemilik proyek.
- **Acceptance Criteria**: Seluruh dokumen foundation disetujui pemilik proyek.
- **Status**: 🟢 **Completed** — diterima final oleh pemilik proyek pada 2026-08-11.

## Phase 2 — Environment Setup — 🟢 COMPLETED
- **Tujuan**: Menyiapkan lingkungan pengembangan lokal & repositori.
- **Prerequisite**: Phase 1 selesai.
- **Pekerjaan**: Install Laravel 13 project, install Vue 3 project (Vite), setup `.env`, setup database lokal, setup Git repository & branch strategy.
- **Output**: Boilerplate backend & frontend berjalan lokal (`php artisan serve`, `npm run dev`).
- **Testing**: Kedua aplikasi dapat diakses tanpa error di localhost.
- **Acceptance Criteria**: Tim dapat clone repo dan menjalankan aplikasi mengikuti README dalam < 15 menit.
- **Status**: 🟢 **Completed** — Laravel 13 (v13.29.0) & Vue 3+Vite terinstal dan
  terverifikasi berjalan (`php artisan serve`, `npm run dev`), koneksi MariaDB
  (`simonev_db`) berhasil, `.env.example` & `README.md` diperbaiki agar konsisten
  dengan baseline sehingga proses clone-and-run dapat diikuti sesuai dokumentasi,
  selesai pada 2026-08-31.
  
## Phase 3 — Database Design & Migration — 🟢 COMPLETED
- **Tujuan**: Mengimplementasikan ERD baseline menjadi migration Laravel.
- **Prerequisite**: Phase 2 selesai.
- **Pekerjaan**: Migration seluruh tabel inti (units, users, performance_structure, indicators, indicator_versions, targets, realizations, dst.), seeder master data awal.
- **Output**: Skema database berjalan via `php artisan migrate`, seeder dasar.
- **Testing**: Migration & rollback berjalan tanpa error; constraint FK konsisten.
- **Acceptance Criteria**: Skema sesuai ERD baseline, direview oleh Database Chat.
- **Status**: 🟢 **Completed** — 29 tabel (21 baru + 1 alter `users`) terimplementasi sesuai
  ERD final hasil CR-002 (Planning Document Lineage) dan CR-003 (Fleksibilitas &
  Kategori Indikator). Rollback penuh (21 migration) dan re-migration tervalidasi
  tanpa error. Seeder 5 tabel master data (`units_of_measure`, `formulas`,
  `reporting_periods`, `measurement_directions`, `indicator_categories`) terverifikasi
  idempotent. `roles`/`user_roles` tetap ditunda ke Phase 5 sesuai baseline. Selesai
  pada 2026-09-02.

## Phase 4 — Laravel Backend Foundation — 🟢 COMPLETED
- **Tujuan**: Menyiapkan struktur dasar backend (base controller, response helper, exception handler, service layer pattern).
- **Prerequisite**: Phase 3 selesai.
- **Pekerjaan**: Base API response format, error handling global, struktur folder `app/Services`, `app/Http/Requests`.
- **Output**: Kerangka backend siap dipakai fitur.
- **Testing**: Endpoint health-check berjalan dengan format response standar.
- **Acceptance Criteria**: Format response API konsisten sesuai `CLAUDE.md` §API Conventions.
- **Status**: 🟢 **Completed** — `ApiResponseTrait` + `BaseController` + exception handler global (`bootstrap/app.php`) terimplementasi, format response `{success,data,message}`/`{success,errors,message}` konsisten di jalur sukses maupun error. Endpoint health-check (`GET /api/v1/health`) berjalan dan terverifikasi via `curl` + 2 Feature Test (`php artisan test --filter=HealthTest`, 2 passed). Pola Service Layer (tanpa Repository, Controller → Service → Eloquent Model) disepakati dan didemonstrasikan via `HealthCheckService`. `app/Http/Requests/` (folder fisik) sengaja ditunda ke Phase 6 — belum ada domain data nyata di Phase 4. Selesai pada 2026-09-03.

## Phase 5 — Authentication & RBAC — 🟢 COMPLETED
- **Tujuan**: Login/logout, Sanctum token, role & permission (9 role: Super Admin, Admin, Operator, Kepala Sub Bidang, Kepala Bidang, Sekretaris,Kepala Dinas, Pimpinan, Publik — per CR-001).
- **Prerequisite**: Phase 4 selesai; keempat TBD CR-001 (§CLAUDE.md §8) idealnya sudah dijawab sebelum seeder permission final ditulis — jika belum, seeder permission untuk item TBD dibuat menyusul, jangan diasumsikan.
- **Pekerjaan**: Setup Sanctum, setup Spatie Permission, seeder role & permission (termasuk permission Sekretaris: review/koreksi/approve-rekap/reject-rekap, dan permission Super Admin: user.manage/system.manage), middleware proteksi route.
- **Output**: API auth berjalan; RBAC matrix (CR-001) ter-enforce di backend.
- **Testing**: Feature test login/logout/akses ditolak sesuai role; testkhusus memastikan Approve Sekretaris tidak menghasilkan status final.
- **Acceptance Criteria**: Semua 9 role dapat login dan hanya mengakses modul sesuai matrix RBAC (`docs/architecture/README.md` §2.4).
- **Status**: 🟢 **Completed** — Sanctum + Spatie Laravel-Permission terinstal dan dimigrasi. 9 role, 36 permission (32 assignable + 4 TBD CR-001 sengaja unassigned) ter-seed sesuai RBAC Matrix penuh. Middleware alias (`role`/`permission`/`role_or_permission`) terdaftar, dibuktikan bekerja end-to-end via endpoint internal `/api/v1/test-permission`. 10 Feature Test lulus (24 assertions), termasuk 2 test constraint kritis: Sekretaris tidak memiliki `realization.finalize.kadis` (hanya `kepala_dinas`). Audit `$fillable` `unit_id` pada model `User` dilakukan — diputuskan **tidak** ditambahkan (parameter otorisasi, bukan field netral; menunggu Service User Management terkait CR-001 TBD-4). Endpoint bisnis (target/realisasi/dll.) sengaja belum dibuat — tetap scope Phase 6+. Selesai pada 2026-09-04.

## Phase 6 — Master Data — 🟢 COMPLETED
- **Tujuan**: CRUD satuan, formula, periode pelaporan, unit/bidang.
- **Prerequisite**: Phase 5 selesai.
- **Output**: Modul master data berfungsi penuh, diproteksi sesuai klasifikasi RBAC (master data kritis vs operasional).
- **Acceptance Criteria**: CRUD tersedia untuk seluruh 4 entitas scope Phase 6; validasi mencegah duplikasi/hapus data terpakai; permission middleware diterapkan sesuai RBAC Matrix final; role dengan permission dapat mengakses, role tanpa permission ditolak.
- **Catatan sinkronisasi wording (2026-09-10)**: Wording awal fase ini ("Admin only", "Admin dapat CRUD seluruh master data") ditulis sebelum RBAC Matrix final (CR-001) memperkenalkan klasifikasi master data kritis vs operasional secara terpisah. Wording di atas telah diselaraskan dengan baseline RBAC final sebagai sumber kebenaran authorization — lihat `CLAUDE.md` §15 dan `CHANGELOG.md` [2026-09-10] untuk detail keputusan (Decision Record: sinkronisasi ROADMAP vs RBAC Matrix Phase 5).
- **Status**: 🟢 **Completed** — CRUD penuh untuk `units_of_measure`, `formulas`, `reporting_periods`, dan model baru `Unit` (`units`) terimplementasi dengan pola Controller→FormRequest→Service→Model. `formulas` diproteksi `master-data-kritis.manage`/`.view` (assignment Phase 5, tidak diubah); `units_of_measure`/`reporting_periods`/`units` diproteksi `master-data-operasional.manage` (TBD-1 CR-001 tetap terbuka, permission ini sengaja belum di-assign ke role manapun). Delete guard eksplisit di Service (cek referensi `indicator_versions`, HTTP 409 terstruktur) untuk 3 entitas; `units` memakai deactivate/activate (tidak ada physical delete) dengan guard user aktif & child aktif. 30 Feature Test baru lulus, ditambah regresi penuh 40 test/78 assertion tanpa kegagalan. Selesai pada 2026-09-10.

## Phase 7 — Performance Structure — ⚪
- **Tujuan**: CRUD struktur berjenjang Tujuan–Sasaran–Program–Kegiatan–Sub Kegiatan dengan versioning.
- **Prerequisite**: Phase 6 selesai.
- **Acceptance Criteria**: Struktur dapat diubah tanpa merusak histori; hierarki tervalidasi (tidak circular).

## Phase 8 — Indicator Management — ⚪
- **Tujuan**: CRUD indikator per level struktur + indicator_versions (satuan/formula/periode).
- **Prerequisite**: Phase 7 selesai.
- **Acceptance Criteria**: Revisi konfigurasi indikator membentuk versi baru, versi lama tetap tersimpan.

## Phase 9 — Target Management — ⚪
- **Tujuan**: Penetapan & revisi target per indicator_version per periode, **eksekusi eksklusif oleh Admin** berdasarkan dokumen perencanaan resmi (Renstra/RKPD Perubahan/Renstra Perubahan/Perjanjian Kinerja Perubahan), setelah pembahasan bersama Admin + bidang terkait (CR-001, AD-2).
- **Prerequisite**: Phase 8 selesai. Open Question CR-001 #3 (kewajiban approval Kadis atas revisi target) idealnya dijawab sebelum acceptance criteria approval final ditulis.
- **Pekerjaan tambahan (CR-001)**: referensi dokumen perencanaan pada proses penetapan target (field/tabel referensi, additive — tidak mengubah skema target existing); permission check: hanya Admin yang bisa eksekusi CRUD target; Kabid/Sekretaris hanya punya akses "ikut pembahasan" (bukan endpoint eksekusi).
- **Acceptance Criteria**: Revisi target wajib alasan; versi lama tetap terhubung ke realisasi lama; hanya Admin yang dapat mengeksekusi penetapan/revisi target (di-enforce di level API, bukan hanya UI).

## Phase 10 — Realization Management — ⚪
- **Tujuan**: Input realisasi Operator + perhitungan otomatis capaian/deviasi/status via formula engine.
- **Prerequisite**: Phase 9 selesai.
- **Pekerjaan tambahan (CR-001)**: dukung Admin sebagai operator backup lintas bidang — input oleh Admin tercatat di audit trail dengan action type eksplisit (`backup_operator_input`), bukan tampak seolah-olah input Operator biasa.
- **Acceptance Criteria**: Perhitungan capaian sesuai formula yang dikonfigurasi (bukan hard-coded), teruji untuk minimal 3 tipe formula; input backup oleh Admin tercatat dan tertelusuri jelas di audit trail.

## Phase 11 — Validation & Approval Workflow — ⚪
- **Tujuan**: Alur status Draft → Kasubbid → Kabid → **Sekretaris (Review/Koreksi/Approve/Reject, non-final)** → Kadis (final), dengan audit trail (CR-001, AD-3).
- **Prerequisite**: Phase 10 selesai.
- **Pekerjaan tambahan (CR-001)**: implementasi mekanisme Koreksi Sekretaris (kembalikan → perbaikan oleh pihak berwenang sesuai pemilik data/workflow masing-masing → ajukan ulang → review kembali) — Sekretaris tidak pernah mengubah nilai realisasi secara langsung; Approve Sekretaris menghasilkan status "direkomendasikan ke Kadis" (bukan status final); UI wajib membedakan status ini dari "Disahkan".
- **Acceptance Criteria**: Setiap transisi status tercatat lengkap (aktor, waktu, catatan); reject/koreksi mengembalikan ke pihak berwenang yang benar (bukan selalu Operator/Kasubbid/Kabid — mengikuti pemilik data); status setelah Approve Sekretaris secara jelas bukan status final; hanya Kadis yang dapat menghasilkan status "Disahkan".

## Phase 12 — Dashboard & Analytics — ⚪
- **Tujuan**: Dashboard internal per role + grafik (ApexCharts) + filter multi-dimensi.
- **Prerequisite**: Phase 11 selesai.
- **Acceptance Criteria**: Seluruh grafik pada spesifikasi UI/UX baseline tersedia & filter berfungsi.

## Phase 13 — Supporting Data — ⚪
- **Tujuan**: Modul data pendukung (DSSD, Kemiskinan, SPIP, SAKIP, IKU, IKD, Investasi) dengan skema fleksibel.
- **Prerequisite**: Phase 12 selesai (dapat paralel dengan Phase 12 bila kapasitas tim memungkinkan).
- **Acceptance Criteria**: Kategori data pendukung baru dapat ditambahkan Admin tanpa perubahan kode/migration.

## Phase 14 — Public Portal — ⚪
- **Tujuan**: Portal publik read-only.
- **Prerequisite**: Phase 12 selesai.
- **Acceptance Criteria**: Tidak ada endpoint publik yang dapat mengubah data (diverifikasi via security test); hanya data berstatus "Dipublikasikan" yang tampil.

## Phase 15 — Reports & Export — ⚪
- **Tujuan**: Export PDF/Excel per filter/struktur/periode.
- **Prerequisite**: Phase 12 selesai.
- **Acceptance Criteria**: Laporan PDF/Excel sesuai data dashboard yang difilter, dapat diunduh tanpa error untuk dataset besar.

## Phase 16 — Notifications — ⚪
- **Tujuan**: Notifikasi in-app (dan email di fase lanjutan) untuk validasi tertunda & revisi target.
- **Prerequisite**: Phase 11 selesai.
- **Acceptance Criteria**: Notifikasi muncul real-time/near-real-time saat status berubah.

## Phase 17 — Testing & Quality Assurance — ⚪
- **Tujuan**: Regression test menyeluruh, security review dasar, UAT dengan calon pengguna.
- **Prerequisite**: Phase 6–16 selesai.
- **Acceptance Criteria**: Tidak ada bug kritikal terbuka; UAT disetujui pengguna kunci per role.

## Phase 18 — Deployment & Documentation — ⚪
- **Tujuan**: Deploy ke VPS/on-premise, finalisasi dokumentasi pengguna & admin.
- **Prerequisite**: Phase 17 selesai.
- **Acceptance Criteria**: Aplikasi dapat diakses di environment produksi; buku panduan pengguna tersedia per role.

---
*Perbarui kolom Status setiap fase selesai. Jangan menandai "selesai" hanya karena kode berhasil dibuat — lihat Definition of Done di `docs/architecture/README.md`.*