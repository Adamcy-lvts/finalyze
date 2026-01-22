# AGENTS.md - Finalyze Codebase Guide

## Project Summary
- Product: AI Project Companion (Finalyze) for Nigerian university students.
- Goal: Guide students through final year project/thesis writing with AI assistance (topic to defense).
- Architecture: Laravel 12 backend + Inertia.js + Vue 3 frontend, Vite build.

## Tech Stack
- Backend: Laravel, PHP 8.2, Sanctum, Reverb (WebSockets), Spatie PDF, PhpWord/TCPDF/FPDI.
- AI: openai-php/laravel.
- Frontend: Vue 3, Inertia.js, Vite, Tailwind CSS v4, Tiptap editor, Ziggy routes.
- Realtime: Pusher JS + Laravel Reverb.
- Tooling: ESLint, Prettier, TypeScript, Vite PWA plugin.

## Key Directories
- app/: Laravel app code (Controllers, Models, Services, etc.).
- config/: Laravel configuration.
- database/: Migrations, factories, seeders.
- resources/js/: Vue/Inertia app (pages, components, layouts, composables).
- resources/views/: Blade entrypoints and PDF templates.
- routes/: Route definitions split by domain (web, auth, payment, etc.).
- public/: Public assets.
- storage/: Laravel storage and logs.
- tests/: PHPUnit/Pest tests.

## Primary Entry Points
- routes/web.php: Main web routes and Inertia entry.
- resources/views/app.blade.php: Inertia app shell.
- resources/js/app.ts: Inertia/Vue bootstrapping, Echo/Reverb setup, theme init.
- resources/js/pages/: Inertia page components (e.g., Welcome, Dashboard, Admin).
- vite.config.ts: Vite build configuration.

## Notable Domains (High-Level)
- Projects/Chapters: Project lifecycle and chapter generation/management.
- Topics: Topic generation and public topic workflows.
- AI tooling: Content generation, analysis, suggestions, guidance.
- Defense: Defense prep workflows and slide deck generation.
- Payments: Word package purchases and Paystack integration.
- Admin: Admin dashboards, system settings, audits, user management.

## Useful Commands
- Backend dev: `composer dev`
- Frontend dev: `npm run dev`
- Tests: `composer test`
- Lint/format: `npm run lint`, `npm run format`

## Notes
- Inertia pages live under `resources/js/pages/` with nested feature folders.
- Tailwind is v4 with Vite integration.
- Realtime features rely on Laravel Reverb + Pusher JS config in `resources/js/app.ts`.
- PDF/Word export and document tooling live in backend services/controllers.

