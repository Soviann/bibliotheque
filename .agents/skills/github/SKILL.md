---
name: github
description: "GitHub operations via gh CLI: issues, PRs, CI runs, project board."
---

# GitHub

Use `gh` CLI for GitHub operations. Repo: `Soviann/bibliotheque`. Project board number: `1` (owner: `Soviann`).

## Conventions

- Always `--json field1,field2` for structured output (cheaper than MCP tools)
- `perPage: 5` max unless more needed
- PR titles: French, visible impact (not implementation detail)
- Squash merge: `gh pr merge <n> --squash` (wait for CI first — no `--auto`, no branch protection)
- Deploy: push `v*` tag (triggers `docker-publish.yml` → NAS)
- Never use MCP `list_issues`/`list_pull_requests` to "check" state

## Quick reference

```bash
# Issues
gh issue create --repo Soviann/bibliotheque --title "..." --body "..." --label "bug"
gh issue view <n> --json number,title,body,labels,state

# PRs
gh pr create --title "..." --body "$(cat <<'EOF'
## Summary
- ...

fixes #N
EOF
)"
gh pr checks <n> --watch
gh pr merge <n> --squash --delete-branch

# CI
gh run list --limit 5 --json databaseId,status,conclusion,name
gh run view <id> --log-failed

# Project board (GraphQL — avoid item-list which fetches 200+ Done)
gh api graphql -f query='query { node(id:"PVT_kwHOANG8LM4BObgL") { ... on ProjectV2 { items(first:20) { nodes { id content {...} } } } } }'
```

## Project board — move to In Progress

Status field-id: `PVTSSF_lAHOANG8LM4BObgLzg9IoUA` · Priority field-id: `PVTSSF_lAHOANG8LM4BObgLzg-FnaM`
Status IDs: Backlog=`d55ad18f` · Todo=`31c84745` · InProgress=`7c2874a8` · (Done = automatic on close)
Priority IDs: Urgent=`76f5d51a` · High=`e40c620b` · Medium=`df6c7ff1` · Low=`8d76e9b3`

```bash
gh project item-edit --project-id PVT_kwHOANG8LM4BObgL \
  --id <ITEM_ID> --field-id PVTSSF_lAHOANG8LM4BObgLzg9IoUA \
  --single-select-option-id 7c2874a8
```
