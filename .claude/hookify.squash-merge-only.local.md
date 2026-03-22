---
name: squash-merge-only
enabled: true
event: bash
pattern: git\s+merge\s+(?!--squash\b|main\b|origin/)
action: block
---

**Squash merge required**

This project uses squash merge. Use `git merge --squash <branch>` instead of `git merge <branch>`.
Merging `main` or `origin/*` into your branch (to update it) is allowed.
