---
name: commit
description: Create clean, well-scoped git commits with French conventional commit messages.
user_invocable: true
---

# Commit

## Steps

1. **Gather context** (parallel): `git status`, `git branch --show-current`, `git log --oneline -5`. Skip `git diff HEAD` if you made the changes.
2. **Clean staging**: `git reset HEAD -- . 2>/dev/null` then `git reset HEAD -- docs/plans/ 2>/dev/null` (never commit plans).
3. **Secrets check**: scan for `.env`, credentials, API keys. Warn user if found.
4. **Lint modified files** (before committing, not after each edit):
   - PHP: `ddev exec vendor/bin/php-cs-fixer fix <files>` then `ddev exec vendor/bin/phpstan analyse <files>`
   - TS/TSX: `ddev exec "cd frontend && npx tsc --noEmit"`
5. **Evaluate scope** — split if unrelated changes. Propose plan:
   > 1. **fix(scope): ...** — `file1.php`, `file2.php`
   > 2. **feat(scope): ...** — `Component.tsx`
   > Commit separately or all at once?
6. **Stage and commit** via HEREDOC:
   ```bash
   git add file1.php file2.php && git commit -m "$(cat <<'EOF'
   type(scope): titre

   Détails techniques optionnels.

   Refs #N
   EOF
   )"
   ```
7. **Repeat** for remaining groups.

## Message format

`type(scope): title` — French 3rd-person imperative (`ajoute`, `corrige`, `supprime`).

**Types:** `feat` · `fix` · `chore` · `refactor` · `docs`

**Scope:** domain/module (`enrichment`, `search`, `cron`, `claude`, `frontend`, `api`).

**Title = visible impact**, not implementation detail.

| BAD | GOOD |
|-|-|
| `fix: utilise PATCH au lieu de PUT` | `fix: corrige la perte des tomes` |
| `feat: ajoute CoverSearchService` | `feat: ajoute la recherche de couvertures` |

## Rules

- Always reference the issue: `#N` in body or `fixes #N` to auto-close.
- Trailer: `Co-Built-By: Claude (<random funny quip>)` (Gemini: `Co-Built-By: Gemini (...)`).
- Merges: `--no-ff`.
- Never commit `docs/plans/`, `.env*`, `.claude/session-handoff.md`.
