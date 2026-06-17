# PDF rendering prerequisite

Report PDFs render via spatie/laravel-pdf → Browsershot → headless Chromium.
Every environment that renders PDFs needs Chromium installed and reachable.

- DDEV: `webimage_extra_packages: [chromium]` + `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium`.
- Production: the hand-edited docker-compose image must install `chromium`
  and set `CHROME_PATH=/usr/bin/chromium`. This is NOT in the repo compose —
  apply it to the prod image deliberately and keep it in sync.
