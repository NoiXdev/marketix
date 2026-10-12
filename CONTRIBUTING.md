# Contributing

Thanks for your interest in Marketix! This short guide helps you get started.

## Setup

The local environment is based on DDEV:

```bash
ddev start
ddev composer install
ddev exec npm install
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
```

Running everything at once during development (server, queue, logs and asset
watcher):

```bash
ddev exec composer run dev
```

## Before a pull request

All checks must pass — these are the same ones CI runs:

```bash
ddev exec vendor/bin/pint --test   # PHP code style
ddev exec php artisan test         # Backend tests
ddev exec npm run lint:check       # ESLint
ddev exec npm run format:check     # Prettier
ddev exec npm run test:js          # Frontend tests
ddev exec npm run build            # Type check and production build
```

New logic comes with tests. Bug fixes get a regression test.

CI also builds the container image and scans it for known vulnerabilities, so a
dependency bump that pulls in a flagged package will fail the build.

## Conventions

- **Commits:** [Conventional Commits](https://www.conventionalcommits.org/)
  (`feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`).
  Enforced by CI, and the release notes are generated from them.
- **Branches:** feature branches, no direct work on `main`.
- **Pull requests:** small and focused; describe the motivation and the impact.
  Please flag security-relevant changes in the PR.
- **Migrations:** additive, never destructive.

## Documentation

User and administrator documentation lives at [docs.noix.dev](https://docs.noix.dev/marketix/admin/introduction/)
in a separate repository — if a change affects behaviour people can see, please say so
in the PR so the documentation can follow.

## Security

Please do **not** report vulnerabilities through public issues — report them privately
instead, see [SECURITY.md](SECURITY.md).

## Conduct

Be kind and respectful. We assume good intent and keep communication factual and
constructive.
