# Architecture — SIMONEV

Referensi teknis rinci. Ringkasan aturan mengikat ada di `/CLAUDE.md`; dokumen ini menjelaskan **detail implementasi** dari aturan tersebut.

---

## 1. Baseline Reference

Dokumen desain "Dokumen Analisis dan Desain Sistem — Aplikasi Web Monitoring Kinerja Perangkat Daerah (SIMONEV)" Tahap 1–7 adalah **baseline yang disetujui**. Dokumen ini menambahkan/menyesuaikan baseline tersebut dengan:
- Penambahan role **Kepala Sub Bidang**.
- Detail folder structure implementasi.
- Standar teknis operasional (Git, API, DB, coding, testing, dokumentasi).
- Definition of Done.

Jika terjadi konflik antara dokumen ini dan baseline, dokumen ini yang berlaku untuk hal-hal teknis operasional; baseline tetap berlaku untuk keputusan desain fungsional/database inti kecuali dicatat sebagai Architecture Decision baru di `CLAUDE.md`.

---

## 2. Role: Kepala Sub Bidang — Integrasi ke Desain

**Posisi dalam struktur organisasi**: setiap Bidang (yang membawahi satu atau lebih Kegiatan/Sub Kegiatan) dapat memiliki beberapa Sub Bidang. Kepala Sub Bidang bertanggung jawab atas indikator-indikator pada Sub Kegiatan tertentu di bawah Bidang-nya.

**Model data**: tabel `units` sudah bersifat self-referencing (`parent_unit_id`) pada baseline — Sub Bidang dimodelkan sebagai `unit` dengan `parent_unit_id` mengarah ke Bidang induknya. Kepala Sub Bidang adalah `user` dengan role `kepala_sub_bidang` yang `unit_id`-nya mengarah ke unit Sub Bidang tersebut. **Tidak perlu tabel baru** — cukup memastikan seeder `units` mencakup level Sub Bidang.

**Perubahan pada workflow validasi (lihat juga `CLAUDE.md` §9)**:

```
Draft (Operator)
  → Diajukan
  → Divalidasi Kasubbid   ← BARU
  → Divalidasi Kabid
  → Direkap Sekretaris
  → Disahkan Kadis (final)
```

Kepala Sub Bidang memvalidasi realisasi pada indikator Sub Kegiatan di bawah Sub Bidang-nya **sebelum** diteruskan ke Kepala Bidang. Bila Sub Bidang tidak digunakan pada suatu Bidang (struktur organisasi flat), tahap ini dapat dilewati secara konfigurasi per Bidang — bukan dihapus dari sistem, tapi dinonaktifkan per konteks (field `requires_kasubbid_validation` pada `units` level Bidang).

**RBAC Matrix (updated, menggantikan matrix pada baseline)**:

| Fitur | Admin | Operator | Kasubbid | Kabid | Sekretaris | Kadis | Pimpinan | Publik |
|---|---|---|---|---|---|---|---|---|
| Kelola Master Data | CRUD | – | – | – | – | – | – | – |
| Kelola Struktur & Indikator | CRUD | Lihat | Lihat (sub bidangnya) | Usul Revisi | Lihat | Setuju Revisi | Lihat | – |
| Input Realisasi | CRUD | Create/Edit (miliknya) | Lihat | Lihat | Lihat | Lihat | Lihat | – |
| Validasi Realisasi | – | – | Approve/Reject (sub bidangnya) | Approve/Reject (bidangnya) | Rekap | Sahkan Final | – | – |
| Dashboard Internal | Penuh | Terbatas (bidangnya) | Sub bidangnya | Bidangnya | Lintas Bidang | Penuh | Ringkasan strategis | – |
| Publikasi ke Publik | Ya | – | – | – | – | – | – | – |
| Portal Publik | – | – | – | – | – | – | – | Lihat |
| Manajemen Pengguna | CRUD | – | – | – | – | – | – | – |
| Audit Log | Lihat | – | – | – | – | Lihat | – | – |

Permission Laravel (Spatie) yang perlu ditambahkan pada Phase 5: `realization.validate.kasubbid`, `structure.view.own-subunit`, disamping permission role lain yang sudah ada di baseline.

---

## 3. Folder Structure

### Backend (Laravel)
```
backend/
├─ app/
│  ├─ Http/
│  │  ├─ Controllers/Api/V1/        # 1 controller per resource
│  │  └─ Requests/                  # FormRequest per aksi (Store, Update, Validate, dst.)
│  ├─ Models/
│  ├─ Services/                     # business logic (formula engine, versioning, approval flow)
│  ├─ Policies/                     # authorization per model
│  └─ Notifications/
├─ database/
│  ├─ migrations/
│  └─ seeders/
├─ routes/
│  └─ api.php
├─ tests/
│  └─ Feature/
└─ .env.example
```

### Frontend (Vue 3)
```
frontend/
├─ src/
│  ├─ features/                     # feature-based: target/, realisasi/, indikator/, dashboard/, publik/, dll.
│  │  └─ <feature>/
│  │     ├─ components/
│  │     ├─ pages/
│  │     ├─ store.js                # Pinia store, actions return {success, message, data, errors}
│  │     └─ api.js
│  ├─ components/common/            # komponen bersama (Button, Table, Modal, dll.)
│  ├─ layouts/                      # DashboardLayout, PublicLayout
│  ├─ router/
│  ├─ assets/styles/                # CSS variables tema instansi
│  └─ App.vue
└─ vite.config.js
```

### Dokumen Proyek
```
/
├─ README.md
├─ ROADMAP.md
├─ CLAUDE.md
├─ CHANGELOG.md
└─ docs/
   └─ architecture/
      └─ README.md   (dokumen ini)
```

---

## 4. Git Rules & Development Workflow

- **Branch strategy**: `main` (stabil/production-ready) ← `develop` (integrasi) ← `feature/<phase>-<nama-fitur>` (mis. `feature/p10-realization-input`).
- **Commit message**: `<tipe>(<scope>): <deskripsi singkat>` — tipe: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`. Contoh: `feat(realization): tambah validasi kasubbid`.
- **Pull Request wajib** untuk merge ke `develop`/`main`, minimal 1 review (self-review diperbolehkan untuk tim kecil, tapi checklist DoD tetap wajib dicek).
- Tidak ada commit langsung ke `main`.
- Setiap PR menyertakan: deskripsi perubahan, fase roadmap terkait, checklist testing.

---

## 5. Standards

### API Standard
Lihat `CLAUDE.md` §7. Tambahan: versi API di path (`/api/v1`), breaking change API wajib bump ke `/api/v2` + dicatat di `CHANGELOG.md`.

### Database Standard
- Tabel `snake_case` plural, kolom `snake_case`, foreign key `<singular>_id`.
- Setiap tabel transaksi: `created_at`, `updated_at`; tabel versi: `valid_from`, `valid_to`, `is_active`.
- Index pada seluruh foreign key dan kolom yang sering difilter (mis. `year`, `status`, `unit_id`).

### Coding Standard
Lihat `CLAUDE.md` §6. Tambahan: setiap Service class fokus 1 domain (mis. `TargetVersioningService`, `AchievementCalculatorService`), maksimal ~300 baris — pecah bila lebih.

### Testing Standard
- Minimal 1 Feature Test per endpoint API: happy path, validasi gagal, permission ditolak.
- Modul kritikal (formula engine, versioning, approval workflow) wajib test tambahan untuk edge case (mis. revisi target di tengah periode berjalan).

### Documentation Standard
- Setiap endpoint API didokumentasikan (nama, method, path, request, response) — format bebas (markdown/Postman collection), disimpan di `docs/api/`.
- `CHANGELOG.md` diperbarui setiap fase roadmap selesai.

---

## 6. Definition of Done

Sebuah fitur/fase **tidak dianggap selesai** hanya karena kode berhasil dibuat. Fitur dianggap selesai jika seluruh berikut terpenuhi:

- [ ] Kode berjalan tanpa error
- [ ] API bekerja sesuai kontrak yang didokumentasikan
- [ ] Frontend terhubung dan berfungsi end-to-end
- [ ] Validasi input bekerja (backend & frontend)
- [ ] Permission/RBAC bekerja sesuai matrix
- [ ] Error handling bekerja (pesan jelas, tidak expose stack trace ke user)
- [ ] Database konsisten (tidak ada orphan record, versi/histori tidak rusak)
- [ ] Testing dilakukan (minimal sesuai Testing Standard)
- [ ] Dokumentasi diperbarui (`CHANGELOG.md`, dan `docs/api/` bila relevan)
- [ ] Acceptance Criteria pada `ROADMAP.md` untuk fase terkait terpenuhi

Pola implementasi wajib per fitur:

```
Requirement → Design → Database → Backend API → API Testing
→ Frontend Integration → UI Testing → Feature Testing
→ Regression Check → Documentation → Acceptance
```