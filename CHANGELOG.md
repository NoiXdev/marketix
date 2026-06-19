## [1.1.1](https://github.com/NoiXdev/marketix/compare/v1.1.0...v) (2026-06-19)


### Bug Fixes

* **geoip:** raise memory limit so database extraction doesn't fail ([70a816d](https://github.com/NoiXdev/marketix/commit/70a816df6e39657eb72d815adb94ff5ee080d3ad))

## [1.1.0](https://github.com/NoiXdev/marketix/compare/v1.0.3...v1.1.0) (2026-06-19)


### Features

* **activity-log:** add ActivityRecorder helper for manual events ([1152394](https://github.com/NoiXdev/marketix/commit/1152394ad65b36b1c940eeb6236d7a0356fdbc39))
* **activity-log:** add project-tagging and redaction concern ([6d0fb71](https://github.com/NoiXdev/marketix/commit/6d0fb7197e5e39a6a92ebe9f7a377b12fd4f178f))
* **activity-log:** admin audit view with filters ([4761ae8](https://github.com/NoiXdev/marketix/commit/4761ae83f2b42b7eb9cf30acaa46c8f9e4b3d322))
* **activity-log:** auto-log Domain, QrCode, Pixel, Project ([69c405a](https://github.com/NoiXdev/marketix/commit/69c405a7b334190ad01e93622d087694ef0c95ab))
* **activity-log:** auto-log Url changes with password redaction ([dc0c14f](https://github.com/NoiXdev/marketix/commit/dc0c14f755116bc8aa77c543d0e0e71c35d5e66c))
* **activity-log:** install spatie activitylog with project_id column and custom model ([0aa06ec](https://github.com/NoiXdev/marketix/commit/0aa06ec31a5d45251ac2a261eca704aad12a5e7d))
* **activity-log:** log membership and invitation events ([219c564](https://github.com/NoiXdev/marketix/commit/219c564d58b3374df3fcf1e5db6fabf09162ed96))
* **activity-log:** log security events (login, password, 2FA, passkeys) ([8b584d5](https://github.com/NoiXdev/marketix/commit/8b584d50ba28b81362c7be49e8f3597ed3549159))
* **activity-log:** per-resource history panel with lazy loading ([08f170f](https://github.com/NoiXdev/marketix/commit/08f170fc6321933d7df26b14cbb446f22cba0c31))
* **activity-log:** project activity feed page ([925b936](https://github.com/NoiXdev/marketix/commit/925b936823e3e94e3534690fee319bf1ecca2e8c))
* **activity-log:** schedule daily activity log pruning (365 days) ([3edd461](https://github.com/NoiXdev/marketix/commit/3edd461894d616e1142c7da8c492ae930d249fa5))
* add profile edit page route and controller ([5f7162b](https://github.com/NoiXdev/marketix/commit/5f7162baaf266cc470bb75cc8bc442b6b9d08ad5))
* add profile edit page UI and menu link ([4ff220b](https://github.com/NoiXdev/marketix/commit/4ff220bd537e9350dcf8ab6ba35e46b90103bc64))
* **admin:** add Horizon link to admin sidebar ([335c87e](https://github.com/NoiXdev/marketix/commit/335c87e61231c3effe020a94d864443a8b38b590))
* **auth:** add forced password change page and endpoint ([09a7227](https://github.com/NoiXdev/marketix/commit/09a72275b4eb39aef7cbd5f7dc896a26af1b88a8))
* **auth:** add passkey buttons to login and challenge ([0d87694](https://github.com/NoiXdev/marketix/commit/0d87694194d151bfd5638990f3218ced8626791e))
* **auth:** add passkey second-factor and rename endpoints ([6fbf2c2](https://github.com/NoiXdev/marketix/commit/6fbf2c240c31bc3d6b72f87d2f36621ee8862a92))
* **auth:** add TOTP enable/confirm/disable/recovery management ([1b5f730](https://github.com/NoiXdev/marketix/commit/1b5f730c66cf9682a1947f3cf531c096df5f2a50))
* **auth:** add TOTP support service ([2cae1c2](https://github.com/NoiXdev/marketix/commit/2cae1c2f1a9e55b8ba573017c3c30afb4ea1b940))
* **auth:** add two-factor columns to users ([2c38052](https://github.com/NoiXdev/marketix/commit/2c380524cf324e5121699e0bd3b59981659e0451))
* **auth:** gate flagged users onto forced password change ([f478576](https://github.com/NoiXdev/marketix/commit/f47857650a2cf4ed8bab4c66a29190500b7b73c0))
* **auth:** gate login behind TOTP challenge ([3cc2eae](https://github.com/NoiXdev/marketix/commit/3cc2eaeb0a781987c182cf91491e701efd9f6aee))
* **auth:** install laravel/passkeys and wire User model ([7e85ef2](https://github.com/NoiXdev/marketix/commit/7e85ef24361c66e69d06c289dd7ab84e73b43611))
* **console:** add marketix:create-admin command ([ac83caa](https://github.com/NoiXdev/marketix/commit/ac83caa9bda3c37ce6b330c0a4900ae13aa6ad2d))
* **domains:** add /.well-known/marketix signature route ([445cefb](https://github.com/NoiXdev/marketix/commit/445cefb8ffdb39e0286b2ee7169c96fd5b5f84d0))
* **domains:** add CheckDomainStatusJob as single status write path ([3d7f045](https://github.com/NoiXdev/marketix/commit/3d7f045c2196735bae2886850a139090b0e5184b))
* **domains:** add DNS resolver and certificate reader seams ([ab4685a](https://github.com/NoiXdev/marketix/commit/ab4685a21442d49d4021778e1e3ad3b7d00497c9))
* **domains:** add DomainStatusChecker service ([3fe3d86](https://github.com/NoiXdev/marketix/commit/3fe3d86cf562145145206d9e6fb8c3ac1761cea6))
* **domains:** add on-demand status check endpoint and expose appDomain ([3e9ae6d](https://github.com/NoiXdev/marketix/commit/3e9ae6d5621dab9e8361aced73b36d90254e6af8))
* **domains:** add status columns, derived status accessor, factory ([7f677bf](https://github.com/NoiXdev/marketix/commit/7f677bf6e3fe5403860e4faa7aeb78bf1be0ed98))
* **domains:** check status on create and every 15 minutes ([4073738](https://github.com/NoiXdev/marketix/commit/40737380e2104b29524acf43aebe3052d95098ae))
* **domains:** info box, status pills, and check-now UI ([ee166f5](https://github.com/NoiXdev/marketix/commit/ee166f5811850b64d204c357c856b443efd35304))
* **invitations:** add last_sent_at column and canResend() helper ([af66eea](https://github.com/NoiXdev/marketix/commit/af66eeab0f880c2bdd6360320f754d52e2ee599a))
* **invitations:** add resend button and expired badge to team page ([ce1aec3](https://github.com/NoiXdev/marketix/commit/ce1aec34ee8b1c4b1a8e65ce8ae8f2ff2c153b6c))
* **invitations:** add resend endpoint and surface expired invites ([ac71aeb](https://github.com/NoiXdev/marketix/commit/ac71aeb761df44f6c498d4ce76e4d31eeae3ccd9))
* **layout:** show app version in admin and auth layouts ([19b12a5](https://github.com/NoiXdev/marketix/commit/19b12a5cdae001b2b52f95cf8c51ec39dd962eed))
* **layout:** show app version in sidebar ([18bf983](https://github.com/NoiXdev/marketix/commit/18bf983447a59bf8b771089d293941b7453600da))
* **models:** add HasUlids to all owned models ([7d79024](https://github.com/NoiXdev/marketix/commit/7d790245ea750630dc6ab6e01ab70c4eaf5fce5e))
* **profile:** add 2FA and passkey management UI ([c924a9b](https://github.com/NoiXdev/marketix/commit/c924a9b65d871fee1133c3bd3cfd6b745daa8d91))
* rename commands with marketix prefix ([52e5ef0](https://github.com/NoiXdev/marketix/commit/52e5ef07c6023c942f46c4b19e3ceb19e38eb415))
* **theme:** add cycling ThemeToggle to sidebar ([6423aa5](https://github.com/NoiXdev/marketix/commit/6423aa59a3e889380f4a96e087e6028a3cf342a5))
* **theme:** add theme helper module and useTheme hook ([47f349b](https://github.com/NoiXdev/marketix/commit/47f349b8837eadd2da8648e677d999105564caea))
* **theme:** enable class-based dark mode with anti-flash script ([b91b9a9](https://github.com/NoiXdev/marketix/commit/b91b9a9efab850b0ef83326dbd809aff1f021e8f))
* **ui:** replace native delete confirms with SweetAlert2 ([1010fb2](https://github.com/NoiXdev/marketix/commit/1010fb21d909978b7f551eae0e5145cefa846119))
* **users:** add force_password_change column and cast ([6f8f9be](https://github.com/NoiXdev/marketix/commit/6f8f9be07a40817bb6096442a0102987ce508d9d))
* **users:** admin send password reset link action ([e94a9e4](https://github.com/NoiXdev/marketix/commit/e94a9e4ab069c9f9aaa42d59115e25b612b3da2b))
* **users:** persist force_password_change from admin user form ([f2a14e3](https://github.com/NoiXdev/marketix/commit/f2a14e3a35deed0094aecb35d1790641c88506ad))
* **users:** sectioned user edit page with projects and security actions ([87c4366](https://github.com/NoiXdev/marketix/commit/87c4366563a9b655faa604bb059b7e6dc1b3914d))
* **users:** show flash feedback on user edit page ([dfb74fa](https://github.com/NoiXdev/marketix/commit/dfb74fa8a0f41ffef50b20c6359e2e280bdeea54))
* **users:** user-centric project membership endpoints ([8fe5fd5](https://github.com/NoiXdev/marketix/commit/8fe5fd5325bebf80b37a4641323c87e64ab58fa9))
* validate profile password change with current password ([d58c58e](https://github.com/NoiXdev/marketix/commit/d58c58ec924bbf9e640a4e59a6c04cff9dc37dfa))


### Bug Fixes

* **activity-log:** assert non-empty history in test, fix QrCodes edit spacing ([c873146](https://github.com/NoiXdev/marketix/commit/c8731462b989622de32cd6ed30afd9721ca48559))
* **activity-log:** expose v5 attribute_changes as 'changes' in toFeedArray ([d08574d](https://github.com/NoiXdev/marketix/commit/d08574d4e0439b5a444253dc109df64be279cf96))
* **activity-log:** guard null activity when logging disabled; show project in admin feed ([70992e6](https://github.com/NoiXdev/marketix/commit/70992e60164381774c6c1dbe50db868d5711fb6b))
* **activity-log:** use ULID morphs for activity_log subject/causer ([cbfa083](https://github.com/NoiXdev/marketix/commit/cbfa083b418482c2640cb717d605da5cd383295d))
* **activity-log:** use v5 beforeActivityLogged hook and attribute_changes ([31e22d0](https://github.com/NoiXdev/marketix/commit/31e22d0347fe6d999327e1d620b7d320fbeb893b))
* **auth:** rate-limit 2FA challenge endpoints and address review findings ([0131d7e](https://github.com/NoiXdev/marketix/commit/0131d7ee12819ff63ad03afbafbc9319de9a8ff0))
* **auth:** register reset password URL for custom reset route ([4da2a60](https://github.com/NoiXdev/marketix/commit/4da2a60e9a3360443860519695922ac829d3dea3))
* **demo:** resolve projects by name instead of hardcoded integer ids ([fe42ed8](https://github.com/NoiXdev/marketix/commit/fe42ed8dc8d036cc6ed5791335d5578ccf9eabb7))
* **domains:** harden reachability check against SSRF and tighten cert matching ([5edc23f](https://github.com/NoiXdev/marketix/commit/5edc23fe336989951c9208bdb4e72f62e1d4723d))
* **profile:** disable 2FA disable/regenerate buttons while processing ([14d2b91](https://github.com/NoiXdev/marketix/commit/14d2b91ee3aba4bbebe5c348b45f12524cb912fe))
* **stats:** trust reverse proxy so real client IP is recorded ([59537cc](https://github.com/NoiXdev/marketix/commit/59537ccf2d8b9aa838d7f3c92995c9b6b9f8d7fa))

## [1.0.3](https://github.com/NoiXdev/marketix/compare/v1.0.2...v1.0.3) (2026-06-18)


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

