# GEMINI.md — Gemini CLI Governance & Instructions

File ini berisi instruksi mengikat khusus untuk **Gemini CLI** agar bekerja selaras dengan tata kelola proyek SIMONEV.

## 1. Supreme Source of Truth
- **`CLAUDE.md`** di root proyek adalah **Sumber Kebenaran Utama (Supreme Governance)**.
- Setiap kali memulai sesi baru atau menerima instruksi, Gemini CLI **WAJIB** membaca `CLAUDE.md` terlebih dahulu untuk memahami status proyek, aturan coding, arsitektur, dan RBAC.
- Jangan pernah menduplikasi isi `CLAUDE.md` ke dalam berkas ini. Berkas ini murni pengatur perilaku operasional Gemini CLI.

## 2. Strict Workflow & Constraints
Gemini CLI wajib mematuhi aturan berikut dalam setiap tugas:
1. **Ikuti Roadmap**: Kerjakan tugas secara ketat mengikuti urutan fase pada `ROADMAP.md`. Jangan melompati fase atau mengimplementasikan fitur masa depan lebih awal.
2. **Jangan Mengubah Baseline**: Dilarang mengubah keputusan arsitektur, skema database, atau business process yang sudah disepakati tanpa melalui alur **Change Management Process** (lihat `CLAUDE.md` §17).
3. **Options API Only**: Saat menulis komponen Vue 3, selalu gunakan **Options API**. Penggunaan Composition API dilarang keras.
4. **Hormati Git Rules**:
   - Jangan pernah melakukan commit langsung ke `main` atau `develop`.
   - Gunakan branch sesuai format: `feature/<phase>-<nama-fitur>`.
   - Gunakan pesan commit terstandar: `<tipe>(<scope>): <deskripsi>`.
   - Dilarang keras melakukan commit atau push tanpa instruksi/persetujuan eksplisit dari pengguna.
5. **Penanganan TBD**: Jangan pernah membuat asumsi sendiri untuk fungsionalitas yang masih berstatus **TBD** di `CLAUDE.md` §8. Jika menemukan area TBD yang diperlukan untuk implementasi, **STOP** dan tanyakan kepada pengguna.

## 3. Alur Kerja Implementasi (Plan-Act-Validate)
Untuk setiap instruksi implementasi (Directive):
- **Plan**: Tulis draf rencana implementasi dan strategi pengujian sebelum menyentuh kode.
- **Act**: Lakukan perubahan kode secara saksama (*surgical edits*) menggunakan alat `replace` untuk meminimalkan polusi token.
- **Validate**: Jalankan pengujian (linter, compiler, test suite) untuk memastikan kode bebas error dan memenuhi kriteria **Definition of Done** di `docs/architecture/README.md` §6.

## 4. Verification Checkpoint
Sebelum menyatakan suatu sub-tugas atau fase selesai, pastikan checklist berikut terpenuhi:
- [ ] Kode bebas error (compile & runtime).
- [ ] Otorisasi/RBAC sesuai matriks untuk modul terkait.
- [ ] Validasi input berjalan di backend (FormRequest) & frontend.
- [ ] Feature test minimal (happy path + failure path) telah ditulis dan lulus uji.
- [ ] Berkas `CHANGELOG.md` diperbarui.
