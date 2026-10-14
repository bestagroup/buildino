# Iran address reference data

This directory contains a local, pinned copy of the province, real-city and county
datasets from [sajaddp/list-of-cities-in-Iran](https://github.com/sajaddp/list-of-cities-in-Iran).
The upstream data release is **3.1.2**, based on administrative divisions through
the end of Solar Hijri **1404**. It is a community-maintained projection, not a
live government registry. New administrative changes require a reviewed update.

- Commit: `ace21bb251f96ced350bbc01541acffd71121feb`.
- Retrieved: 2026-10-14.
- `provinces.json`: all 31 provinces from this release.
- `cities-filtered.json`: all 1,481 real-city records from this release.
- `counties.json`: county names, used to distinguish same-name cities.
- `source-manifest.json`: unchanged upstream release metadata.
- `provenance.json`: local import metadata and SHA-256 hashes of the vendored files.

The upstream project licenses these files under **GNU GPL v3.0**; the full
upstream license is retained in [LICENSE](LICENSE). These are separately attributed
third-party data files. Upstream conversion/application code is not copied here.
Preserve this directory's attribution and license when redistributing its data.

The upstream `cities` projection also contains 191 urban zones. Registration uses
`cities-filtered` to avoid presenting urban zones as separate cities. No real-city
record is deduplicated: distinct IDs and county labels distinguish the two
same-name city pairs within their provinces.

## Integration

`App\Services\System\IranLocations` provides the shared catalog and canonical
name lookup. Registration renders the complete options locally; JavaScript narrows
the city list to the selected province. No geographic API or remote fetch is needed
at runtime. Without JavaScript, city options remain grouped by province, and the
server still rejects an invalid parent-child pair.

The registration form submits `province_id` and `city_id` from this catalog. The
server validates their relationship and derives the canonical `province` and
`city` labels. The existing database columns and pending-registration contract
continue to store those labels. Catalog IDs are reference identifiers, not new
foreign keys in `complexes`. Name-only requests remain supported, with Persian
letter/spacing normalization and province-city validation.

## Updating the catalog

1. Review a newer upstream release, its official source year and license.
2. Pin an exact commit and replace the three JSON data files, manifest and license
   together. Do not download mutable `main` data during application execution.
3. Regenerate the SHA-256 hashes in `provenance.json` and update this document's
   version, date and counts. Review renamed cities, moved cities and identifiers.
4. Run `php artisan test --filter="IranLocationsTest|SelfRegistrationWebTest"`.
5. Check dependent selection, Persian search, error restoration and mobile layout
   in the browser before shipping the updated catalog.
