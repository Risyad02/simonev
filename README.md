# SIMONEV — Sistem Monitoring dan Evaluasi Kinerja Perangkat Daerah

Aplikasi web untuk memantau **target dan realisasi kinerja** 1 (satu) Perangkat Daerah, berbasis struktur Renstra/Renja/RKPD, dengan dukungan histori penuh atas perubahan indikator, target, dan struktur program.

## Status Proyek

**Phase 2 — Environment Setup** (lihat `ROADMAP.md`)

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

## Repository

- GitHub: `Risyad02/simonev` (private)
- Branch strategy: `main` (stable) · `develop` (integration) · `feature/*` (development)

```bash
git clone https://github.com/Risyad02/simonev.git
cd simonev
git checkout develop
```

## Struktur Peran (Roles)

Admin, Operator, **Kepala Sub Bidang**, Kepala Bidang, Sekretaris, Kepala Dinas, Pimpinan, Publik.

## Cara Menjalankan (diisi setelah Phase 2 — Environment Setup)

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend
cd frontend
npm install
npm run dev
```

## Kontribusi & Alur Kerja

Lihat `docs/architecture/README.md` §Git Rules & Development Workflow sebelum membuat perubahan.

## Model Kerja Proyek

Proyek ini dikembangkan lintas beberapa "chat spesialis" (Backend, Frontend, Database, UI/UX, QA/Testing, Deployment), dikoordinasikan oleh satu **Master Project Chat**. Setiap chat spesialis menerima *Context Handoff Prompt* yang merujuk ke dokumen-dokumen di atas — bukan proyek terpisah.
