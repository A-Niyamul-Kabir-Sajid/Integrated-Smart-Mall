# map-service: instructions for AI coding agents

This is the separate map service of the Integrated Smart Mall project (decision D-020 in `../DOCS/DECISIONS.md`). Follow the root project's rules in `../CLAUDE.md` and `../AGENTS.md`: Laravel conventions, PHPDoc and explicit types, Pint, a test for every change, a manual check in `../guide/TestByYourself/`, and no new dependencies without the user's approval.

What is different here:

- It is its own Laravel 13 app with its own `vendor/`, `.env` and SQLite database. Run commands from this folder.
- Code uses the default Laravel layout (`app/Models`, `app/Services`, `app/Http/...`), not the main app's `app/Modules`.
- Tests: `php artisan test --compact`, `node --test tests/js/annotations.test.mjs` and `node --test tests/js/ar.test.mjs` (one file per run; `node --test tests/js/` does not work here).
- The AR directions page is plain JavaScript in `public/ar/` (WebXR, three.js from the same pinned CDN); its maths lives in `public/ar/ar-math.js` so Node can test it.
- The 3D viewer is plain JavaScript in `public/viewer/` (three.js from the jsDelivr CDN, pinned to 0.170.0); there is no npm build.
- The API contract is described in `README.md`; change it only together with the main app's client (`../app/Modules/MapsNavigation`).
