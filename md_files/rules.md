# rules.md — MarketLink

## What to use
- PHP with **PDO** and prepared statements for every query
- `password_hash()` / `password_verify()` for all passwords
- Native PHP sessions for auth state (`$_SESSION['user_id']`, `role`, etc.)
- Bootstrap for layout/grid — customize colors/fonts on top of it, don't
  ship default Bootstrap look
- Leaflet.js + OpenStreetMap for all map features
- Plain vanilla JS or jQuery for interactivity (cart updates, filters,
  form validation feedback)

## What to avoid
- No frameworks (Laravel, Symfony, Slim, etc.) — plain PHP only
- No OOP-based code (no classes for business logic) and no MVC pattern —
  write plain function-based PHP (procedural style: functions + includes)
- No Composer packages for things pages can do natively (routing, ORM,
  templating) — keep the stack simple and explainable
- No Google Maps API (requires billing/API key) — use Leaflet + OSM
- No payment gateway integration — out of scope
- No string-concatenated SQL — ever
- No inline styles scattered everywhere — keep CSS in `/assets/css`
- No copying a ready-made admin dashboard template wholesale — build the
  UI from scratch using `design.md` as the guide

## Error handling
- Wrap DB calls in try/catch; on failure show a generic user-facing error
  and log the real exception message (don't expose stack traces to users)
- Validate all form input server-side, even if there's also client-side
  JS validation
- Show clear inline error messages next to the relevant form field, not
  just a generic alert
- Every page that requires a role checks the session before running any
  other logic (fail closed, redirect to login if session/role is missing)

## Boundaries for AI (Claude Code or any coding agent)
- Don't introduce a new framework, library, or architecture pattern
  without asking first — stick to what's in `Architecture.md`
- Don't invent features beyond `PRD.md` / `phases.md` — flag ideas instead
  of silently adding scope
- Don't fully auto-generate the project report/documentation — draft
  sections only, Asim writes the final explanation himself (he needs to
  defend this in a judge interview)
- Don't skip `memory.md` updates — log what was completed and what's next
  after every work session
- When unsure about a design/data decision not covered in these docs,
  ask rather than assume
