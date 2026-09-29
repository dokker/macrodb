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
- `App\Services\MealLogger` is the only write path for meals (`log`, `updateItemGrams`, `deleteItem`; deleting the last item removes the meal). `App\Services\DailySummary` (`forDate`, `forRange`) computes totals per meal, the applicable `daily_targets` row and the deviation, cutting days in Europe/Budapest.
- `App\Services\FoodCatalog` backs `create_food`, `create_recipe` and `get_food_by_barcode` (local DB first, then `OpenFoodFactsClient`, whose hit is persisted). OFF products without a name or the four core macros are treated as not found.
- REST routes live in `routes/api.php` (prefix `/api`, unversioned) with Form Requests, thin controllers and resources. They sit behind `auth:api` (Passport).
- The MCP server is `App\Mcp\Servers\MacroDbServer` at `/mcp` (`routes/ai.php`) with nine tools in `app/Mcp/Tools`, one per spec tool. Tools reuse the Form Request `rules()` for validation and the same services and resources as REST. `/mcp` sits behind `auth:api` with `Mcp::oauthRoutes()`; the OAuth consent screen is `resources/views/mcp/authorize.blade.php` and needs the session login at `/login`.
- `App\Services\DailyTargets` owns target lookup and writes. Targets are set in the PWA (`#/targets`, `GET/POST /api/targets`, `DELETE /api/targets/{id}`) or with `artisan targets:set {kcal} {protein} {carbs} {fat} --from=`. There is no MCP tool for it on purpose (the spec lists none).
- Auth is Passport alone (no Sanctum): OAuth for the MCP connector, a personal access token for REST (`artisan api:token`, needs a personal client: `artisan passport:client --personal`). The single account is seeded from `MACRODB_USER_EMAIL`/`MACRODB_USER_PASSWORD`; run `passport:keys` on deploy. REST tests use `Passport::actingAs`.
- Micronutrients (`Nutrients::MICROS`: saturated fat, sodium, potassium, calcium, iron, magnesium, vitamin C, vitamin D) are nullable per-100 g columns on `foods`, filled only when the source has them, and exposed under `micros` in every nutrient array. Like fiber, a total is null as soon as one contributing item lacks the value. OFF gives grams, so the client converts to the units in the key names.
- Importers are manual artisan commands (no scheduler entry, since the filtered dumps are uploaded by hand): `usda:import {dir}` reads the FDC CSVs (`food.csv`, `food_nutrient.csv`; foods without energy are skipped, missing macros default to 0) and `off:import {file}` reads a JSON Lines dump (optionally .gz) filtered by `--countries` (default `en:hungary`). Both upsert on `(source, external_id)` via `FoodImporter`, so re-runs are safe. `OpenFoodFactsProduct` is the single OFF-to-food mapping, shared with the live client.
- `add_food_alias` (MCP) / `POST /api/foods/{id}/aliases` lets the LLM attach Hungarian names to imported English foods.
- Photo flow: `POST /api/meals/photo` (multipart `image`, optional `note`) -> `PhotoDraftBuilder` asks the `VisionProvider` (bound to `GeminiVisionProvider`, config `services.gemini`, env `GEMINI_API_KEY`/`GEMINI_MODEL`) for `{name_hu, name_en, grams, confidence}`, matches each through `FoodSearch` (Hungarian name first, then English) and returns an unsaved draft whose items are shaped for `POST /api/meals`. Vision failures answer 502. The default model id is unverified against the live API; check it when first using a real key.
- Frontend PWA: a vanilla-JS single page app in `resources/js` (hash router; views `today`, `add`, `history`; `api.js` wraps `/api`), styled with Tailwind v4 tokens in `resources/css/app.css` (light/dark via CSS variables; use the `card`/`btn-*`/`field`/`chip` utilities). The Blade shell is `resources/views/app.blade.php`, served at `/` behind the web session login. `CreateFreshApiToken` turns that session into the `laravel_token` cookie so the SPA calls `/api` with no token handling. `public/sw.js` caches only built assets and the shell (no offline logging); `public/manifest.webmanifest` + `public/icons` make it installable. Barcode scanning uses `html5-qrcode` (lazy-loaded; camera needs HTTPS or localhost, manual entry is the fallback). Build with `vendor/bin/sail npm run build`. Tailwind cannot see class names assembled from variables (`bg-${x}`), so pass full class names.
- A photo-matched tray item keeps its `alternatives` and shows a "Nem ez? Csere…" select in the tray; swapping keeps the estimated grams.
- `usda:import` also accepts the Survey (FNDDS) export, whose `food_nutrient.nutrient_id` holds legacy nutrient numbers (208, not 1008); the command translates them through `nutrient.csv`. The FDC CSV folders live in the git-ignored `.assets/`.
- `StarterRecipesSeeder` (`artisan db:seed --class=StarterRecipesSeeder`, not part of `DatabaseSeeder`) adds Hungarian aliases to the chosen USDA foods (looked up by FDC `external_id`) and creates the five starter recipes. It is idempotent and skips a recipe whose ingredients are not imported. The grams and finished weights are drafts to be reviewed.

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
