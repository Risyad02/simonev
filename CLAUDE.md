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

Admin, Operator, **Kepala Sub Bidang**, Kepala Bidang, Sekretaris, Kepala Dinas, Pimpinan, Publik.

RBAC matrix lengkap (termasuk Kepala Sub Bidang): lihat `docs/architecture/README.md` §5.

## 9. Workflow Validasi (updated)

```
Operator (input)
  → Kepala Sub Bidang (validasi tingkat sub kegiatan/sub bidang)
  → Kepala Bidang (validasi tingkat bidang/kegiatan)
  → Sekretaris (rekap lintas bidang)
  → Kepala Dinas (pengesahan final)
```
Setiap tahap dapat mengembalikan (reject) ke tahap sebelumnya disertai catatan. Status "Disahkan" terkunci.

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
> **Phase 2 — Environment Setup** — belum dimulai, menunggu instruksi lanjut dari pemilik proyek

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
