# AI Collaboration & Workflow Standards — SIMONEV

Dokumen ini mendefinisikan standar kolaborasi, pembagian peran, dan resolusi konflik ketika proyek SIMONEV dikembangkan menggunakan beberapa asisten kecerdasan buatan (seperti Claude dan Gemini) lintas sesi *chat spesialis*.

---

## 1. Source of Truth Utama
- **`CLAUDE.md`** di root proyek bertindak sebagai **Central Project Memory** dan *Source of Truth* mutlak bagi seluruh asisten AI.
- Dokumen pendukung lain di bawah direktori `docs/` (misalnya `docs/architecture/README.md`) memberikan panduan teknis yang lebih rinci dan tidak boleh bertolak belakang dengan `CLAUDE.md`.
- Jika terdapat inkonsistensi antara dokumen baseline atau instruksi pengguna dengan `CLAUDE.md`, AI wajib menghentikan aktivitas coding (**STOP**) dan meminta klarifikasi atau mengajukan *Change Request* formal.

---

## 2. Alur Kerja Kolaborasi AI (AI Workflow)
Setiap pengerjaan fitur baru oleh AI harus mengikuti siklus hidup **Research -> Strategy -> Execution**, dengan alur pelaksanaan internal sebagai berikut:

```
[Requirement Study] (Membaca CLAUDE.md & ROADMAP.md)
       ↓
[Technical Design & Risk Analysis] (Menganalisis dampak ke DB, BE, FE)
       ↓
[Database & Code Verification] (Mengecek keselarasan relasi dan endpoint)
       ↓
[Surgical Implementation] (Menulis kode secara terfokus, mis. Laravel/Vue Options API)
       ↓
[Feature Testing] (Menulis unit/feature test untuk memverifikasi fungsionalitas)
       ↓
[Documentation & Changelog] (Mencatat perubahan di CHANGELOG.md)
       ↓
[DoD Checkpoint] (Verifikasi kesiapan sebelum serah terima tugas)
```

---

## 3. Aturan Git untuk Asisten AI
Untuk menjaga integritas riwayat kode, seluruh asisten AI wajib mematuhi aturan Git berikut:
- **No Direct Commits**: Jangan pernah melakukan commit langsung ke branch utama (`main` atau `develop`).
- **Standardized Branching**: Pekerjaan harus diselesaikan pada branch fitur, dengan format penamaan: `feature/<phase>-<nama-fitur>` (contoh: `feature/p2-setup`).
- **Staging Control**: Jangan pernah menggunakan perintah `git add .` secara tidak terfokus. Hanya tambahkan berkas yang secara sadar dibuat atau dimodifikasi untuk tugas tersebut.
- **Commit Drafting**: AI harus menyusun draf pesan commit yang terperinci menggunakan format standar: `<tipe>(<scope>): <deskripsi singkat>` (contoh: `docs(ai): tambah panduan workflow AI`).
- **No Force Operations**: Perintah git yang bersifat destruktif atau memaksa (`git push --force`) dilarang keras.
- **Explicit Approval for Commits**: AI dilarang mengeksekusi commit secara mandiri tanpa ada instruksi dan persetujuan eksplisit dari pengguna.

---

## 4. Penanganan Konflik Antar-AI
Dalam skenario di mana asisten AI yang berbeda memberikan saran atau implementasi yang saling bertentangan:
1. **Priority Rule**: Utamakan keputusan baseline yang tertulis di `docs/architecture/README.md` dan `CLAUDE.md`.
2. **Analysis Over Action**: AI tidak boleh langsung menimpa kode atau arsitektur yang diusulkan oleh AI sebelumnya. AI yang mendeteksi konflik wajib menyajikan perbandingan analitis yang mencakup:
   - Kelebihan & kekurangan masing-masing pendekatan.
   - Dampak terhadap performa, keamanan, dan batas waktu roadmap.
   - Kompatibilitas terhadap PHP 8.5/Laravel 13 dan Vue Options API.
3. **STOP & Approve**: Tunggu keputusan final dan persetujuan tertulis dari pengguna sebelum menerapkan perubahan apa pun yang menyelesaikan konflik tersebut.

---

## 5. Kapan AI Harus Menghentikan Pekerjaan (STOP)?
AI wajib segera menghentikan proses pengerjaan (*hard stop*) dan meminta klarifikasi jika:
1. **Menemukan Requirement TBD**: Menjumpai fungsionalitas kritis yang belum diputuskan dalam spesifikasi (misalnya, 4 poin TBD di `CLAUDE.md` §8) yang menghalangi penulisan logika bisnis secara akurat.
2. **Kebutuhan Perubahan Database Baseline**: Terdapat kebutuhan untuk menambahkan kolom baru, mengubah relasi, atau restrukturisasi tabel pada ERD baseline yang belum disepakati.
3. **Terjadi Inkonsistensi Dokumentasi**: Menemukan perbedaan fungsional antara dokumen baseline (`Dokumen_Desain...docx`) dengan aturan di `CLAUDE.md` atau `docs/architecture/README.md`.
4. **Gagal Pengujian Berulang**: Jika perbaikan bug atau test mengalami kegagalan berturut-turut lebih dari 3 kali, AI wajib berhenti melakukan perbaikan cepat (*patching*) dan menyajikan evaluasi ulang komprehensif atas arsitektur tersebut.

---

## 6. Verification & Definition of Done (DoD)
Setiap pekerjaan dianggap selesai (*Verified*) jika dan hanya jika seluruh kriteria berikut terpenuhi:
- [ ] Kode berjalan tanpa error dan lulus uji linter/type-check.
- [ ] Implementasi backend (FormRequest, Services, Policies) dan frontend (Vue Options API, Pinia, Axios) terhubung end-to-end.
- [ ] Otorisasi/RBAC bekerja sesuai matriks 9 role.
- [ ] Automated testing (minimal feature test happy path & failure path) lulus 100%.
- [ ] Perubahan terdokumentasi dengan baik di `CHANGELOG.md` dan `docs/api/` (jika relevan).
- [ ] Pengguna memberikan persetujuan final setelah melalui langkah verifikasi bersama.
