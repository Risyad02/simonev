# SIMONEV — Sistem Monitoring dan Evaluasi Kinerja Perangkat Daerah

Aplikasi web untuk memantau **target dan realisasi kinerja** 1 (satu) Perangkat Daerah, berbasis struktur Renstra/Renja/RKPD, dengan dukungan histori penuh atas perubahan indikator, target, dan struktur program.

## Status Proyek

**Phase 1 — Project Foundation** (lihat `ROADMAP.md`)

## Dokumen Proyek

| Dokumen | Isi |
|---|---|
| `CLAUDE.md` | Aturan inti proyek / project memory — wajib dibaca setiap chat spesialis baru |
| `ROADMAP.md` | 18 fase pengembangan, status, acceptance criteria |
| `CHANGELOG.md` | Riwayat perubahan/keputusan proyek |
| `docs/architecture/README.md` | Arsitektur, stack, RBAC, ERD ringkas, folder structure, standar teknis, Definition of Done |
| Dokumen Desain Baseline (Word) | Analisis kebutuhan s.d. roadmap awal (Tahap 1–7) — baseline yang disetujui |

## Teknologi

Laravel 13 · Vue 3 (Options API) · Pinia · MySQL/MariaDB · Sanctum · Spatie Permission · ApexCharts — lihat `CLAUDE.md` §2 untuk detail & alasan.

## Struktur Peran (Roles)

**Super Admin**, **Admin**, Operator, **Kepala Sub Bidang**, Kepala Bidang, Sekretaris (= Sekretaris Dinas/Sekdin), Kepala Dinas, Pimpinan, Publik. Detail RBAC: `docs/architecture/README.md` §2.

## Cara Menjalankan

### Prasyarat
- PHP 8.3–8.5, Composer 2.x
- Node.js ≥ 18, npm
- MariaDB/MySQL aktif (mis. via XAMPP)

### Backend
```bash
cd backend
composer install
cp .env.example .env
```
Buat database terlebih dahulu di MariaDB (sekali saja, sebelum migrate):
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS simonev_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Lalu:
```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
Backend berjalan di `http://127.0.0.1:8000`.

### Frontend
```bash
cd frontend
npm install
npm run dev
```
Frontend berjalan di `http://localhost:5173`.

## Kontribusi & Alur Kerja

Lihat `docs/architecture/README.md` §Git Rules & Development Workflow sebelum membuat perubahan.

## Model Kerja Proyek

Proyek ini dikembangkan lintas beberapa "chat spesialis" (Backend, Frontend, Database, UI/UX, QA/Testing, Deployment), dikoordinasikan oleh satu **Master Project Chat**. Setiap chat spesialis menerima *Context Handoff Prompt* yang merujuk ke dokumen-dokumen di atas — bukan proyek terpisah.