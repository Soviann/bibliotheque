---
name: technical-writer
description: "Create or update documentation following Bibliotheque conventions."
---

# Technical Writer

## Conventions

- **Language**: French for docs/guides/comments, English for code identifiers and CLAUDE.md
- **Audience for plans**: solo dev (Claude) — what to do (files, logic, order), not how (no code blocks)
- **Audience for `patterns.md`**: LLM — tables over prose, token-efficient
- **Changelog**: Keep a Changelog format in French (sections: `Added`/`Changed`/`Fixed`/`Removed` under `## [Unreleased]`)

## Files to update

| File | When | Audience |
|---|---|---|
| `CHANGELOG.md` | Every meaningful change | User/PO |
| `.claude/memory/patterns.md` | New entity/enum/service/route/component/command | LLM |
| `docs/guide-deploiement-nas*.md` | Deployment/infra change | Human + Claude |
| `CLAUDE.md` | New rule, pattern, or tech | Claude (English only) |
| `docs/plans/*.md` | Non-trivial upcoming work | Self (gitignored, delete after merge) |

## Style

- Tables > prose. Bullets > paragraphs.
- File paths as references: `backend/src/Service/X/Foo.php:45`, not inline code dumps.
- No code snippets in plans.
- Progressive disclosure: summary first, details second.
- Changelog entries: `- **Name**: Description` under the right section.
