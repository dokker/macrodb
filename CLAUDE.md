# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md
@docs/spec.md

`AGENTS.md` holds the Laravel Boost guidelines (Sail, Pest, Pint and test commands, skills in `.claude/skills/`). Boost regenerates it (`vendor/bin/sail artisan boost:update`), so put project-specific notes here, not there. The Boost MCP server is configured in `.mcp.json`.

## Local environment

- There is no native PHP on the dev machine; everything runs through Sail. Before `vendor/` exists (fresh clone), install dependencies with `docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 composer install`.
- App: http://localhost:8089, Mailpit: http://localhost:8027, MariaDB from the host: port 33067. The ports are chosen so they don't clash with the `vrs` project and the wpdev stack.
- Tests run against the MariaDB `testing` database (created by Sail's init script, selected in `phpunit.xml`), not SQLite, because search relies on MariaDB collation behaviour.
- Deployment is the existing shared hosting described in the spec, not Laravel Cloud. Ignore the Cloud hints in `AGENTS.md`.

## Code map

- `App\Services\FoodSearch` searches foods (name + aliases) and recipes together and returns `FoodSearchResult`s with per-100 g nutrients and named portions. It is the shared layer behind `search_foods` and `GET /foods`.
- `App\Support\Nutrients` is an immutable nutrient amount (`scale`, `plus`). Fiber/sugar become null as soon as any contributing source lacks them. Use it for every macro calculation (recipes, meals, summaries).
- The enums in `App\Enums` hold the stored values: `FoodSource` (off/usda/custom), `MealType` and `InputMethod` (Hungarian values from the spec).
- The Laravel pluralizer treats "food" as uncountable, so `Food` sets `#[Table('foods')]` and migrations must use `constrained('foods')`.
- Not built yet: MCP server/tools (`laravel/mcp` is installed), REST routes, auth, importers (OFF/USDA), summaries, daily-target logic.

## Decisions already made (see the spec's "Nyitott kérdések" section)

- Backend: Laravel (PHP 8.5) + MariaDB. Production is existing shared hosting with a cron-driven Laravel scheduler and a database-backed queue. Docker is only for local development.
- Frontend (later): a PWA with in-browser camera and barcode scanning (html5-qrcode or ZXing). There are no native builds.
- Photo recognition (phase 2): the Gemini free tier, behind a `VisionProvider` interface.
- Daily targets and deviation reporting are part of v1.

## Architectural invariants

These span several sections of the spec and are easy to violate:

- **One app, two entry points.** The MCP server (Streamable HTTP endpoint inside the same Laravel app, used by the LLM) and the REST API (everything else) both call one shared application/service layer. Keep business logic out of controllers and MCP tool classes. Every MCP tool has a matching REST route (table in the spec).
- **The backend computes macros; the LLM never estimates them.** The LLM only searches, picks foods, converts portions to grams and logs. The vision model likewise only names items and estimates grams.
- **Units:** nutrients are stored per 100 g (per 100 ml for liquids). Meal items are logged in grams. Totals are derived from these two values.
- **Summaries are computed, not stored.** Daily and range summaries come from a view or query over `meal_items` + `foods`/`recipes`, so corrections show up immediately.
- **Recipes use the cooked weight.** A recipe's per-100 g values are its ingredient totals divided by `total_weight_g` (the finished dish's weight, since water is lost during cooking), not by the sum of raw ingredient weights.
- **All food sources go into one `foods` table**, distinguished by `source` (Open Food Facts, USDA FDC, own entries). USDA names are English, so Hungarian names go into `food_aliases`, which the LLM can also add to.
- **Barcode lookup:** check the local DB first. Only on a miss, call the live Open Food Facts API and persist the result. Bulk data comes from dumps that are filtered locally and then uploaded; the full OFF dump is too large for the host.
- **Search is Hungarian and substring-based.** Typical queries are partial Hungarian words ("zab" → zabpehely, zabtej, USDA "Oats" through its alias). `FoodSearch` therefore uses ranked LIKE matching (exact name, then name prefix, then word prefix, then substring) instead of FULLTEXT, which misses compound parts ("tej" in "zabtej") and words under 3 characters. Every query word must match the name or an alias. Matching is case- and accent-insensitive through `utf8mb4_unicode_ci`. Results include recipes/dishes. English-named foods are only findable once they have a Hungarian `food_aliases` row.
- **Photo flow never saves directly.** `POST /meals/photo` returns a draft. Saving goes through the same `log_meal` path (`POST /meals`) as every other input method.
- **Single user.** Domain tables have no `user_id` and there is no multi-tenancy; don't add user scoping. The `users` table exists only for authentication: it holds a single seeded account for the MCP connector's OAuth login (and to own the REST API token).
- **Time:** store all timestamps in UTC. Daily summaries cut days by `Europe/Budapest`.
- **Auth:** OAuth for the MCP endpoint (Claude remote connectors require it), a personal API token for REST.
- **Enum values in the spec are Hungarian:** `meal_type` is reggeli/ebéd/vacsora/snack and `input_method` is szöveg/vonalkód/fotó.
