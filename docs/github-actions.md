# GitHub Actions

## Pinning third-party actions

Resolve the floating major tag to its current immutable commit SHA:

```bash
gh api repos/actions/setup-node/commits/v7 --jq '.sha'
````

Then identify the exact release tag for that SHA:

```bash
gh api repos/actions/setup-node/tags \
  --jq '.[] | select(.commit.sha == "<SHA>") | .name'
```

Use the immutable SHA in the workflow and keep the exact release as a comment:

```yaml
uses: actions/setup-node@820762786026740c76f36085b0efc47a31fe5020 # v7.0.0
```

Repeat the same pattern for other Actions by replacing `actions/setup-node` and the major tag.
