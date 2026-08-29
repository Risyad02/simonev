# Architecture — SIMONEV

Referensi teknis rinci. Ringkasan aturan mengikat ada di `/CLAUDE.md`; dokumen ini menjelaskan **detail implementasi** dari aturan tersebut.

---

## 1. Baseline Reference

Dokumen desain "Dokumen Analisis dan Desain Sistem — Aplikasi Web Monitoring Kinerja Perangkat Daerah (SIMONEV)" Tahap 1–7 adalah **baseline yang disetujui**. Dokumen ini menambahkan/menyesuaikan baseline tersebut dengan:
- Penambahan role **Kepala Sub Bidang**.
- Penambahan role **Super Admin** & perluasan kewenangan **Admin** dan **Sekretaris** (CR-001, 2026-08-28).
- Detail folder structure implementasi.
- Standar teknis operasional (Git, API, DB, coding, testing, dokumentasi).
- Definition of Done.

Jika terjadi konflik antara dokumen ini dan baseline, dokumen ini yang berlaku untuk hal-hal teknis operasional; baseline tetap berlaku untuk keputusan desain fungsional/database inti kecuali dicatat sebagai Architecture Decision baru di `CLAUDE.md`.

---

## 2. RBAC — Role Kepala Sub Bidang, Super Admin, Admin, Sekretaris (Integrasi ke Desain)

### 2.1 Kepala Sub Bidang

**Posisi dalam struktur organisasi**: setiap Bidang (yang membawahi satu atau lebih Kegiatan/Sub Kegiatan) dapat memiliki beberapa Sub Bidang. Kepala Sub Bidang bertanggung jawab atas indikator-indikator pada Sub Kegiatan tertentu di bawah Bidang-nya.

**Model data**: tabel `units` sudah bersifat self-referencing (`parent_unit_id`) pada baseline — Sub Bidang dimodelkan sebagai `unit` dengan `parent_unit_id` mengarah ke Bidang induknya. Kepala Sub Bidang adalah `user` dengan role `kepala_sub_bidang` yang `unit_id`-nya mengarah ke unit Sub Bidang tersebut. **Tidak perlu tabel baru** — cukup memastikan seeder `units` mencakup level Sub Bidang.

Bila Sub Bidang tidak digunakan pada suatu Bidang (struktur organisasi flat), tahap validasi Kasubbid dapat dilewati secara konfigurasi per Bidang — bukan dihapus dari sistem, tapi dinonaktifkan per konteks (field `requires_kasubbid_validation` pada `units` level Bidang).

### 2.2 Super Admin & Admin (CR-001, AD-1)

Dua role administrasi terpisah, **tidak digabung**:

- **Super Admin** — tata kelola platform: manajemen pengguna & role/permission, konfigurasi sistem, master data sistem-kritis (definisi role/permission, struktur formula engine), audit tingkat sistem (termasuk aktivitas Admin). Tidak otomatis punya akses CRUD data kinerja operasional.
- **Admin** — tata kelola operasional: CRUD struktur & indikator, **eksekutor tunggal penetapan target** (berdasarkan dokumen perencanaan resmi, setelah pembahasan bersama bidang terkait), dapat menjadi **operator backup lintas bidang** (tercatat eksplisit di audit trail sebagai tindakan backup — action type `backup_operator_input`), publikasi ke portal publik.

Jumlah akun masing-masing role **tidak dibatasi** — dapat ada beberapa Super Admin dan beberapa Admin, jumlah pasti configurable sesuai kebutuhan organisasi (menghindari single point of failure/bottleneck).

**Catatan organisasi (bukan Architecture Decision)**: Bagian Perencanaan/Perencana sebagai kandidat pemegang role Admin — masih rencana, belum final. Role Admin tidak terikat pada unit/orang tertentu secara desain sistem.

### 2.3 Sekretaris (= Sekretaris Dinas/Sekdin — satu role yang sama, CR-001 AD-3)

Sekretaris **bukan** role terpisah dari Sekdin — hanya satu nama role di sistem: **Sekretaris**.

Dua kategori kewenangan:
- **Data operasional milik unit Sekretariat sendiri**: Sekretaris berperan setara Kabid untuk unitnya sendiri (validasi realisasi Sekretariat) — pola RBAC existing, bukan kewenangan baru.
- **Data lintas bidang pada tahap rekap** (monev keseluruhan OPD): Sekretaris memiliki **View/Rekap**, **Review**, **Koreksi**, **Approve**, **Reject** — tapi **tidak** CRUD bebas atas data bidang lain.

**Mekanisme Koreksi** (kewenangan proses bisnis, bukan sekadar anotasi):
```
Sekretaris menandai data perlu koreksi (wajib catatan)
        ↓
data dikembalikan kepada pihak yang berwenang
   (mengikuti pemilik data & workflow masing-masing — TIDAK dikunci
    selalu Operator → Kasubbid → Kabid untuk semua jenis data)
        ↓
perbaikan dilakukan oleh pihak berwenang tersebut (bukan oleh Sekretaris)
        ↓
diajukan kembali → naik lagi melalui tahap validasi → kembali ke Sekretaris
```
Sekretaris **tidak pernah** mengubah nilai realisasi secara langsung — hanya mengubah *status* (mis. `dikembalikan_untuk_koreksi`) + catatan wajib.

**Approve/Reject tahap rekap bersifat non-final**:
- Approve → status data menjadi "direkomendasikan/diteruskan ke Kepala Dinas" (bukan "disetujui final").
- Reject → data dikembalikan ke pihak berwenang (setara alur Koreksi).
- Kepala Dinas tetap menjadi pihak pengesahan final.

**Perubahan pada workflow validasi (lihat juga `CLAUDE.md` §9)**:

```
Draft (Operator)
  → Diajukan
  → Divalidasi Kasubbid
  → Divalidasi Kabid
  → Direkap/Review/Koreksi/Approve/Reject Sekretaris (NON-FINAL)
  → Disahkan Kadis (final)
```

### 2.4 RBAC Matrix (updated — CR-001, menggantikan matrix sebelumnya)

| Fitur | Super Admin | Admin | Operator | Kasubbid | Kabid | Sekretaris | Kadis | Pimpinan | Publik |
|---|---|---|---|---|---|---|---|---|---|
| Manajemen Pengguna & Role/Permission | CRUD | – | – | – | – | – | – | – | – |
| Konfigurasi Sistem | CRUD | – | – | – | – | – | – | – | – |
| Master Data — sistem-kritis | CRUD | Lihat | – | – | – | – | – | – | – |
| Master Data — operasional (satuan, periode, unit/bidang) | **TBD** | **TBD** | – | – | – | – | – | – | – |
| Kelola Struktur & Indikator | Lihat/Audit | CRUD | Lihat | Lihat (sub bidangnya) | Usul Revisi | Lihat | Setuju Revisi **(TBD)** | Lihat | – |
| Kelola Target | Lihat/Audit | CRUD, eksekutor tunggal | – | – | Ikut pembahasan | Ikut pembahasan | **TBD** | Lihat | – |
| Input Realisasi | – | CRUD (backup lintas bidang) | Create/Edit (miliknya) | Lihat | Lihat | Lihat (lintas bidang) | Lihat | Lihat | – |
| Validasi tingkat Sub Bidang | – | – | – | Approve/Reject | – | – | – | – | – |
| Validasi tingkat Bidang | – | – | – | – | Approve/Reject | – | – | – | – |
| View/Rekap lintas bidang | Lihat | Lihat | – | – | – | Ya | Lihat | Ringkasan strategis | – |
| Review (tahap rekap) | – | – | – | – | – | Ya | – | – | – |
| Koreksi (tahap rekap, lihat mekanisme §2.3) | – | – | – | – | – | Ya | – | – | – |
| Approve (tahap rekap, non-final) | – | – | – | – | – | Ya | – | – | – |
| Reject (tahap rekap) | – | – | – | – | – | Ya | – | – | – |
| CRUD data operasional bidang lain | – | (via backup operator) | – | – | – | **Tidak** | – | – | – |
| Pengesahan Final Realisasi | – | – | – | – | – | – | Ya | – | – |
| Publikasi ke Publik | **TBD** | Ya | – | – | – | – | – | – | – |
| Portal Publik | – | – | – | – | – | – | – | – | Lihat |
| Dashboard Internal | Penuh + audit sistem | Penuh (operasional) | Terbatas (bidangnya) | Sub bidangnya | Bidangnya | Lintas Bidang (scope monev) | Penuh | Ringkasan strategis | – |
| Audit Log | Penuh (termasuk log Admin) | Lihat | – | – | – | – | Lihat | – | – |

**TBD terbuka (jangan diimplementasikan sebelum diputuskan)**:
1. Pembagian master data operasional Super Admin vs Admin.
2. Akses publikasi/override Super Admin.
3. Kewajiban approval Kadis atas revisi target.
4. Mekanisme pembuatan Super Admin pertama & berikutnya.

Permission Laravel (Spatie) yang perlu ditambahkan pada Phase 5: `realization.validate.kasubbid`, `structure.view.own-subunit`, `target.manage` (Admin-only), `realization.review.sekretaris`, `realization.correct.sekretaris`, `realization.recommend.sekretaris` (approve non-final), `realization.return.sekretaris` (reject), `system.manage` (Super Admin-only), `user.manage` (Super Admin-only).

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