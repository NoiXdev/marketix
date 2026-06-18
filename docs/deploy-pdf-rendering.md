# PDF rendering prerequisite

Report PDFs render via spatie/laravel-pdf → Browsershot → headless Chromium.
Every environment that renders PDFs needs Chromium installed and reachable.

- DDEV: `webimage_extra_packages: [chromium]` + `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium`.
- Production: the hand-edited docker-compose image must install `chromium`
  and set `LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium`. This is NOT in the repo compose —
  apply it to the prod image deliberately and keep it in sync.

## Production Node/Puppeteer requirement

Browsershot drives Chromium through the **puppeteer** Node package, which is listed as a
runtime dependency in `package.json`. The production image must therefore:

1. **Install Node.js** — puppeteer is a Node package; the PHP process invokes it via `node`.
2. **Ship `node_modules/puppeteer`** — run `npm ci` (NOT `npm ci --omit=dev`) during the
   image build step, because puppeteer is a runtime dependency, not a dev-only tool.
3. **Set these environment variables** so puppeteer uses the system Chromium instead of
   downloading its own binary:
   ```
   PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
   PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
   ```

Note: the prod `docker-compose.yml` is hand-edited (hardcoded env, no `.env`) and is NOT
in this repository. Apply all of the above to the prod image out-of-band and keep it in
sync manually.
