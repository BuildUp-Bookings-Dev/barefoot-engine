# Delta Refresh Smoke Test (Debug-First)

This smoke test validates:

- debug probes for property and availability change-log endpoints
- cron-based delta refresh behavior
- listings visual behavior with non-blocking preflight + availability refinement

## Preconditions

1. Plugin is active.
2. API credentials are saved.
3. At least one full sync succeeded previously.
4. Build is current (`npm run build`).
5. Playwright CLI wrapper is available:
   - `$PWCLI="$HOME/.codex/skills/playwright/scripts/playwright_cli.sh"`

## Artifacts

- Visual screenshots: `output/playwright/smoke/`
- Debug JSON payloads: `output/playwright/smoke/debug/`

## Steps and Accepted Results

1. Build and baseline state

- Command:
  - `npm run build`
  - `/Users/braudypedorsa/Local\ Sites/barefoot/app/public/tools/wp option get barefoot_engine_delta_refresh_state --format=json`
  - `/Users/braudypedorsa/Local\ Sites/barefoot/app/public/tools/wp option get barefoot_engine_availability_state --format=json`
- Accepted:
  - build exits successfully
  - options are readable JSON (default shape is acceptable)

2. Debug endpoint: last-updated

- Endpoint:
  - `POST /wp-json/barefoot-engine/v1/properties/debug/last-updated`
- Accepted:
  - HTTP 200
  - response includes:
    - `endpoint`
    - `last_access_used`
    - `raw_payload`
    - `parsed` with update buckets
    - `duration_ms`
  - parser handles no-change payload without fatal

3. Debug endpoint: last availability changed (normal + test)

- Endpoints:
  - `POST /wp-json/barefoot-engine/v1/properties/debug/last-avail-changed`
  - `POST /wp-json/barefoot-engine/v1/properties/debug/last-avail-changed` with `{"use_test_endpoint":true}`
- Accepted:
  - HTTP 200 for both
  - both return parsed `property_ids` arrays
  - if `GetLastAvailChangedPropertiesTest` is unavailable on the account, debug test mode should transparently fall back to `GetLastAvailChangedProperties` and return:
    - `used_fallback_endpoint: true`
    - `resolved_endpoint: GetLastAvailChangedProperties`
  - no parse errors or fatals

4. Debug endpoint: delta preview

- Endpoint:
  - `POST /wp-json/barefoot-engine/v1/properties/debug/delta-preview`
- Accepted:
  - HTTP 200
  - response includes:
    - `would_update_ids`
    - `would_cancel_ids`
    - `would_invalidate_availability_cache`
    - `availability_changed_ids`
  - endpoint is read-only (no post/meta mutation expected)

5. Deterministic cron run

- Command:
  - `/Users/braudypedorsa/Local\ Sites/barefoot/app/public/tools/wp cron event run barefoot_engine_delta_refresh_run`
  - `/Users/braudypedorsa/Local\ Sites/barefoot/app/public/tools/wp option get barefoot_engine_delta_refresh_state --format=json`
- Accepted:
  - cron hook runs
  - state updates `last_started_at`, `last_finished_at`, `last_status`
  - state contains summary counts
  - lock transient is not stuck after completion

6. Visual smoke: admin properties tab

- URL:
  - `http://barefoot.local/wp-admin/admin.php?page=barefoot-engine&tab=properties`
- Accepted:
  - page renders without JS errors
  - sync cards/status are visible
  - no layout break

7. Visual smoke: listings page (no dates)

- URL:
  - `http://barefoot.local/search-results/`
- Accepted:
  - listings render
  - no persistent skeleton/loading state
  - no hard errors in console

8. Visual smoke: listings page (dated search)

- Trigger dated search via embedded search widget.
- Accepted:
  - `Search in progress` appears
  - skeleton appears while async search is running
  - skeleton disappears after completion
  - results and map settle without persistent loading artifacts

9. Network smoke during dated search

- Confirm requests in browser network:
  - `availability/preflight`
  - `availability/search`
- Accepted:
  - both requests occur for dated searches
  - failure of availability endpoint degrades gracefully to local filtered results

10. Rates freshness sanity

- Check one updated property:
  - `/Users/braudypedorsa/Local\ Sites/barefoot/app/public/tools/wp post meta get <post_id> _be_property_rates --format=json`
- Accepted:
  - rates meta exists and is parseable
  - listing price rendering still works

11. Failure-mode sanity

- Simulate one upstream failure path.
- Accepted:
  - no fatal frontend/admin errors
  - delta state records a meaningful error message
  - lock is released

## Global Acceptance Criteria

- No PHP fatals.
- No uncaught JS exceptions blocking UI.
- Debug probe payloads are stable and parseable.
- Delta cron updates state coherently.
- Listings UI remains responsive while preflight is non-blocking.
