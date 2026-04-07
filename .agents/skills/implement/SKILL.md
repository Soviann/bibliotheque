---
name: implement
description: Full implementation cycle for a GitHub issue — context, plan, TDD, PR, merge. Mandatory for all issue work.
user_invocable: true
---

# Implement GitHub Issue

**IMPORTANT:** Create a task (TaskCreate) for EACH step. Mark `in_progress` → `completed` sequentially. No skipping.

1. **Gather context**: read `.claude/memory/patterns.md`. If no issue number specified, pick highest-priority Todo from the board list in `MEMORY.md` (Urgent > High > Medium > Low, then lowest issue number). Fall back to `gh issue view <n>` only if memory is insufficient.
2. **Evaluate complexity**: straightforward (clear scope, existing patterns) → proceed. Complex (architectural decisions, unclear requirements, multiple valid approaches) → `EnterPlanMode`, get approval.
3. **Branch**: `git checkout main && git pull && git checkout -b <type>/<N>-<short-desc>`
4. **Move issue** to In Progress on project board (see `github` skill for IDs).
5. **Implement with tests** per CLAUDE.md Testing strategy:
   - Bug fix / complex logic → test-first
   - New service / simple logic → code + tests together
   - API endpoint / React component → code first, test after
6. **Verify** via `superpowers:verification-before-completion`: `make test && make lint` (PHPStan + CS Fixer on modified `.php`, tsc on modified `.tsx`).
7. **Update `CHANGELOG.md`** under `## [Unreleased]`.
8. **Update docs** if the change affects deployment, CLI commands, or APIs: grep `docs/` for impacted terms.
9. **Update `.claude/memory/patterns.md`** with new entities/enums/services/routes/components. Skip if nothing new.
10. **Summary + approval** → ask the user before pushing.
11. **Commit and push** via `commit` skill.
12. **Code review** via `superpowers:requesting-code-review`. Fix via `superpowers:receiving-code-review`. Loop until clean. Re-run tests if code changed.
13. **Create PR**: `gh pr create` (only after review is clean). Include `fixes #N`.
14. **Wait for CI**: `gh pr checks <n> --watch`. Merge: `gh pr merge <n> --squash --delete-branch`.
15. **Cleanup**: `git checkout main && git pull && git branch -d <branch>`.
