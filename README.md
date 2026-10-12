# Marketix

**Marketix is a self-hosted marketing suite for short links, QR codes and privacy-friendly visitor statistics — with your own domains, dashboards and scheduled reports.**

[![CI](https://github.com/NoiXdev/marketix/actions/workflows/ci.yml/badge.svg)](https://github.com/NoiXdev/marketix/actions/workflows/ci.yml)
[![Docker image version](https://img.shields.io/docker/v/noixdev/marketix?sort=semver&logo=docker&logoColor=white&label=docker)](https://hub.docker.com/r/noixdev/marketix)
[![Docker pulls](https://img.shields.io/docker/pulls/noixdev/marketix.svg?logo=docker&logoColor=white)](https://hub.docker.com/r/noixdev/marketix)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## What is it?

Marketix turns the links you hand out into something you can measure. Campaign links
live under **your own domains**, print material gets QR codes you can repoint without
reprinting, and every visit is counted on your own instance — no third party in
between, and no raw addresses kept.

Everything is grouped into **projects**, so several brands, customers or teams can work
side by side in one installation without seeing each other's data.

## Features

- **Short links** — custom slugs, expiry dates, password protection, and targeting by
  country or device.
- **Your own domains** — serve links from any domain you control, with redirect rules
  and a live view of certificate and name-server status.
- **QR codes** — reusable templates, your own colours and logo, and versioning so a
  printed code can point somewhere new later.
- **Privacy-friendly statistics** — a lightweight counting script on your own
  instance; visitors are stored as a hash, never as a raw address.
- **Events, goals and funnels** — track the steps that matter, attach revenue, and see
  where people drop out.
- **Live view** — what is happening on your links and sites right now.
- **Site overview** — keep an eye on the sites behind your links, including
  certificate expiry and name-server answers.
- **Dashboards** — assemble the numbers you care about from widgets and arrange them
  per project.
- **Scheduled reports** — a recurring report by email, as a ready-made document.
- **Projects and team** — invitations, per-project permissions, and an activity log of
  who changed what.
- **Modern sign-in** — password with two-factor, or passkeys.
- **Administration** — branding, outgoing mail, file storage and user management from
  the interface.
- **Automation** — revocable API tokens, a documented programming interface, and an
  integration for AI assistants.
- **Multiple languages** — the interface follows the language each user picks.

## Screenshots

> _Screenshots coming soon._

## Documentation

The full documentation lives at **[docs.noix.dev](https://docs.noix.dev/marketix/admin/introduction/)**:

- [Getting started](https://docs.noix.dev/marketix/user/getting-started/) — first steps in the interface
- [Installation](https://docs.noix.dev/marketix/admin/installation/docker/) — running your own instance
- [Operations](https://docs.noix.dev/marketix/admin/operations/) — day-to-day running, upgrades, troubleshooting
- [Reference](https://docs.noix.dev/marketix/reference/faq/) — settings, commands and FAQ

## Self-hosting

Marketix is built to run on your own infrastructure and is shipped as a container. The
[installation guide](https://docs.noix.dev/marketix/admin/installation/docker/) walks
through the setup; [client IP troubleshooting](docs/client-ip-troubleshooting.md) covers
the two cases where a visitor's real address never reaches the application and geo data
stays empty.

## Community & Support

Questions, ideas, or running into something? Join the NoiXdev Discord — the
fastest way to get help and talk to us and other users.

[![Join our Discord](https://img.shields.io/badge/Discord-Join%20the%20server-5865F2?style=for-the-badge&logo=discord&logoColor=white)](https://discord.gg/8mrgcgFbpY)

## Security

Found a vulnerability? Please report it privately — see [SECURITY.md](SECURITY.md).

## Contributing

Contributions are welcome. Get started in [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Marketix is released under the MIT License — see [LICENSE](LICENSE).
