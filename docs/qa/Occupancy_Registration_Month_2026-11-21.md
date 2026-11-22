# Initial occupancy registration month

Initial occupancy uses the first day of its Persian registration month (Asia/Tehran), with resident-count history recorded at the same date. Later relationships retain explicit dates; soft-deleted history prevents reclassification as initial onboarding. Existing relationships are not silently rewritten during calculation.

## Existing records

After deploying this commit, run from the Laravel project directory:

```sh
php artisan occupancies:align-registration-month 9
php artisan occupancies:align-registration-month 9 --apply
```

The first command previews changes for building 9 (Niloufar 46). The second applies them transactionally under building/unit locks. Only units with exactly one non-deleted, active, unended occupancy qualify. Ended relationships or multiple relationships require individual review. Owner relationships are unchanged.

The command derives the month from occupancy.created_at. It uses the earliest recorded count on/after registration, falling back to an existing month-start count; missing evidence aborts the entire transaction. Later count records remain intact. Buildings with issued/closed periods are refused. Calculated periods return to draft and must be calculated again. Repeating the repair does not duplicate histories.

A relationship registered after Mehr 1405 will not count as occupied in Mehr. This is intentional under the requested registration-month policy; do not invent earlier occupancy to suppress validation errors.

No migration or frontend build is needed. Production data correction requires executing the command on the production server; a Git push alone does not run it.

## Validation

Targeted workbook import, registration-month, historical count, occupancy domain/web, allocation, and invitation acceptance tests passed. The unrelated Ghasedak resend test expects `BuildinoInvitation`, whereas existing configuration defaults to `buildinoinvitation`; it fails independently of this date policy and was excluded from the final dependency run. No production repair or live recalculation was executed in this change.
