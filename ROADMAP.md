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

## Phase 2 — Environment Setup — 🟡 NEXT (belum dimulai, menunggu instruksi lanjut)
- **Tujuan**: Menyiapkan lingkungan pengembangan lokal & repositori.
- **Prerequisite**: Phase 1 selesai.
- **Pekerjaan**: Install Laravel 13 project, install Vue 3 project (Vite), setup `.env`, setup database lokal, setup Git repository & branch strategy.
- **Output**: Boilerplate backend & frontend berjalan lokal (`php artisan serve`, `npm run dev`).
- **Testing**: Kedua aplikasi dapat diakses tanpa error di localhost.
- **Acceptance Criteria**: Tim dapat clone repo dan menjalankan aplikasi mengikuti README dalam < 15 menit.

## Phase 3 — Database Design & Migration — ⚪
- **Tujuan**: Mengimplementasikan ERD baseline menjadi migration Laravel.
- **Prerequisite**: Phase 2 selesai.
- **Pekerjaan**: Migration seluruh tabel inti (units, users, performance_structure, indicators, indicator_versions, targets, realizations, dst.), seeder master data awal.
- **Output**: Skema database berjalan via `php artisan migrate`, seeder dasar.
- **Testing**: Migration & rollback berjalan tanpa error; constraint FK konsisten.
- **Acceptance Criteria**: Skema sesuai ERD baseline, direview oleh Database Chat.

## Phase 4 — Laravel Backend Foundation — ⚪
- **Tujuan**: Menyiapkan struktur dasar backend (base controller, response helper, exception handler, service layer pattern).
- **Prerequisite**: Phase 3 selesai.
- **Pekerjaan**: Base API response format, error handling global, struktur folder `app/Services`, `app/Http/Requests`.
- **Output**: Kerangka backend siap dipakai fitur.
- **Testing**: Endpoint health-check berjalan dengan format response standar.
- **Acceptance Criteria**: Format response API konsisten sesuai `CLAUDE.md` §API Conventions.

## Phase 5 — Authentication & RBAC — ⚪
- **Tujuan**: Login/logout, Sanctum token, role & permission (termasuk Kepala Sub Bidang).
- **Prerequisite**: Phase 4 selesai.
- **Pekerjaan**: Setup Sanctum, setup Spatie Permission, seeder role & permission, middleware proteksi route.
- **Output**: API auth berjalan; RBAC matrix ter-enforce di backend.
- **Testing**: Feature test login/logout/akses ditolak sesuai role.
- **Acceptance Criteria**: Semua 8 role dapat login dan hanya mengakses modul sesuai matrix RBAC.

## Phase 6 — Master Data — ⚪
- **Tujuan**: CRUD satuan, formula, periode pelaporan, unit/bidang.
- **Prerequisite**: Phase 5 selesai.
- **Output**: Modul master data berfungsi penuh (Admin only).
- **Acceptance Criteria**: Admin dapat CRUD seluruh master data; validasi mencegah duplikasi/hapus data terpakai.

## Phase 7 — Performance Structure — ⚪
- **Tujuan**: CRUD struktur berjenjang Tujuan–Sasaran–Program–Kegiatan–Sub Kegiatan dengan versioning.
- **Prerequisite**: Phase 6 selesai.
- **Acceptance Criteria**: Struktur dapat diubah tanpa merusak histori; hierarki tervalidasi (tidak circular).

## Phase 8 — Indicator Management — ⚪
- **Tujuan**: CRUD indikator per level struktur + indicator_versions (satuan/formula/periode).
- **Prerequisite**: Phase 7 selesai.
- **Acceptance Criteria**: Revisi konfigurasi indikator membentuk versi baru, versi lama tetap tersimpan.

## Phase 9 — Target Management — ⚪
- **Tujuan**: Penetapan & revisi target per indicator_version per periode.
- **Prerequisite**: Phase 8 selesai.
- **Acceptance Criteria**: Revisi target wajib alasan; versi lama tetap terhubung ke realisasi lama.

## Phase 10 — Realization Management — ⚪
- **Tujuan**: Input realisasi Operator + perhitungan otomatis capaian/deviasi/status via formula engine.
- **Prerequisite**: Phase 9 selesai.
- **Acceptance Criteria**: Perhitungan capaian sesuai formula yang dikonfigurasi (bukan hard-coded), teruji untuk minimal 3 tipe formula.

## Phase 11 — Validation & Approval Workflow — ⚪
- **Tujuan**: Alur status Draft → Kasubbid → Kabid → Sekretaris → Kadis, dengan audit trail.
- **Prerequisite**: Phase 10 selesai.
- **Acceptance Criteria**: Setiap transisi status tercatat lengkap (aktor, waktu, catatan); reject mengembalikan ke tahap sebelumnya dengan benar.

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
