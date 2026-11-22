# Resident portal integration audit

Reference: main/origin/main `108e7ad7`; resident commits `06df4db7`, `28b66661`.

## Evidence

- Fresh `git fetch origin`: no newer main commits at audit time.
- All 27 files introduced or modified by the resident commits match main and origin/main exactly before this fix.
- The public production resident stylesheet, workspace script and portal script returned HTTP 200 and matched the local main content after normalizing line endings.
- The authenticated production route redirects to login without a session; private production UI was not inspected. Browser checks use real Blade output with an isolated SQLite memory database and controlled API responses.

## Reproduced and corrected

1. Mobile short dialogs inherited the global full-viewport minimum height while adding resident margins. Override height within the resident scope and keep long form content scrollable; avoid stretching grid rows.
2. Dark-mode primary buttons used white text on the lighter dark-theme blue. Use the existing dark background token for readable button text.
3. The number formatter ignored new text nodes inserted by dynamic dialog updates. Normalize those nodes while preserving input values and excluded script/style/code content.

These were resident presentation defects, not lost merge changes. No process, API, database or provider style was modified.

## Regression coverage

- Each defect reproduced by a failing real-browser assertion before the fix and passed afterward.
- Expanded resident browser coverage to units, wallet, services and loyalty, using real resource-specific datatable responses.
- Check primary-button contrast in both themes and mobile short-dialog height.
- Disable test-only CSS motion for deterministic final-state checks; production animation is unchanged.
- Final checks: resident browser scenarios at 320/390/1440px; shared portal asset and invitation suites; resident access/detail/lifecycle and facility reservation feature suites.
- Result: 3 browser suites passed, including 33 resident scenario/viewport combinations; 39 feature tests passed (256 assertions). JavaScript/PHP syntax checks and `git diff --check` passed.

Publication: fixes prepared on `fix/resident-dialog-layout`; no production deployment performed.
