# Architectural Decisions

## ADR-001: Repository contains no deployment secrets

### Status

Accepted

### Decision

This repository must be publishable at any time without auditing or rewriting its history.

Deployment-specific information such as credentials, authentication tokens, internal endpoints and environment-specific configuration must never be committed.

Deployed remote filenames and paths are treated as deployment-specific and kept out of the repository. Template filenames may be descriptive and public.

Instead, deployment-specific configuration is injected at deployment time from files outside version control.

### Rationale

This guarantees that:

- no secrets can accidentally leak through Git history;
- the repository can be made public at any time;
- deployments remain reproducible;
- secrets can be rotated independently of the repository.

### Consequences

The repository contains templates and example configuration only.

Every deployment requires a local secrets file (for example `/etc/bredland/secrets.env`) to be created separately.