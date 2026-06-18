## [1.0.3](https://github.com/NoiXdev/marketix/compare/v1.0.2...v) (2026-06-18)


### Features

* add admin mailer settings controller, request, and routes ([4ae9a7f](https://github.com/NoiXdev/marketix/commit/4ae9a7f8c6dd2312847afabcaf5f877271504ee7))
* add admin mailer settings page and sidebar nav ([965330c](https://github.com/NoiXdev/marketix/commit/965330cb6bd8809b8bf1d7f6cc2be0b936ac3172))
* add blade report templates and vendored chart.js ([445d020](https://github.com/NoiXdev/marketix/commit/445d020bce7d15ad3ca4d29c45aec80bc5a80c57))
* add date-range aware aggregation methods ([03204e6](https://github.com/NoiXdev/marketix/commit/03204e6fdd08014fb33f07ad29f4ac4e2f7b3c4c))
* add Download PDF button with range picker to stats pages ([630a608](https://github.com/NoiXdev/marketix/commit/630a6082852ae9afeda9c091afcfe33e7515f6a6))
* add on-demand PDF report download endpoints ([91b2358](https://github.com/NoiXdev/marketix/commit/91b23581716a6ea5692d6bf0f9f1ec85335a802d))
* add Postal transport and MailSettings backed by spatie/laravel-settings ([74849b6](https://github.com/NoiXdev/marketix/commit/74849b61c04d0045c8c627d21fc6da38dd194662))
* add ReportData DTO and ReportDataService builder ([3496910](https://github.com/NoiXdev/marketix/commit/3496910fb73e5b32b2d34466659b73fee47cb879))
* add ReportDateRange value object for report windows ([0d49db3](https://github.com/NoiXdev/marketix/commit/0d49db37b00aaccf0042c0e1c1a98039d4e8c058))
* add TestMail mailable for admin mailer test-send ([a733447](https://github.com/NoiXdev/marketix/commit/a7334479dfe736524b57a315438ed736884598ca))
* install spatie/laravel-pdf and chromium infra for reports ([405acbe](https://github.com/NoiXdev/marketix/commit/405acbed7795d75890ef1e411bded1732b3fb564))
* override mail config from MailSettings at boot ([d9b30a1](https://github.com/NoiXdev/marketix/commit/d9b30a15bd6e6d68e1d23c387c1d6983652fec01))
* remove public registration ([89a5a91](https://github.com/NoiXdev/marketix/commit/89a5a91823f96689d7a7c2ae9b245dcb2fe93aef))


### Bug Fixes

* address final-review findings (prod deploy doc, factory hygiene, range 422, span test) ([91ad450](https://github.com/NoiXdev/marketix/commit/91ad4501a4c52b9f1d5a82960a1f629b1886da67))
* address review findings on mailer settings ([7e2c3a6](https://github.com/NoiXdev/marketix/commit/7e2c3a6dbd9783960d13e12d83a3045acd62d1ab))
* disable settings auto-discovery in favor of explicit registration ([f5e89cc](https://github.com/NoiXdev/marketix/commit/f5e89cc2a02871044919e0f55fa49a946600782f))
* drop spurious link-report heading; tighten template render test ([224ae90](https://github.com/NoiXdev/marketix/commit/224ae9063bdc4dd4d4d2b22eb31ee2fdc073078d))
* keep mail settings fresh under Octane and queue workers ([95bfff8](https://github.com/NoiXdev/marketix/commit/95bfff8c06861bf40287b86927197dc2d29e6670))
* make StatisticFactory project-consistent and fake traefik job in range test ([cdfec5d](https://github.com/NoiXdev/marketix/commit/cdfec5d7447dacb9c1316e071ba74a55b2588adb))
* move puppeteer to runtime deps and correct prod chrome env var ([5c8863a](https://github.com/NoiXdev/marketix/commit/5c8863af787efacc31ef4555eecaec5bcfde6fe8))
* surface flash banners and SMTP field errors on mailer page ([371688b](https://github.com/NoiXdev/marketix/commit/371688b54421be105f7f58ad8da4b3c4742b6bf3))

## [1.0.2](https://github.com/NoiXdev/marketix/compare/v1.0.1...v1.0.2) (2026-06-17)


### Bug Fixes

* adding missing symfony yaml dependencies ([47d3d62](https://github.com/NoiXdev/marketix/commit/47d3d621f1cdd02e24f9a998ed7db272dba58885))
* horizon gate check ([fb00b91](https://github.com/NoiXdev/marketix/commit/fb00b91d11da32ddc2585e0c46860d861b918e46))
* pint cleanup ([9a151cf](https://github.com/NoiXdev/marketix/commit/9a151cfb8466d11c53d6d77034a59bab13f96b95))

## [1.0.1](https://github.com/NoiXdev/marketix/compare/v1.0.0...v1.0.1) (2026-06-17)


### Bug Fixes

* force SSL scheme ([56b8c7e](https://github.com/NoiXdev/marketix/commit/56b8c7ed18e313cc71a143164e65191693ab45b6))

## [1.0.0](https://github.com/NoiXdev/marketix/compare/e725c216767ec0acddee93a913a96e8ebe30480a...v1.0.0) (2026-06-17)


### Features

* adjust composer, package and docker ([f38c56d](https://github.com/NoiXdev/marketix/commit/f38c56db6d20dbd862e4b86cd608cf082bc928eb))
* initial public release ([e725c21](https://github.com/NoiXdev/marketix/commit/e725c216767ec0acddee93a913a96e8ebe30480a))

