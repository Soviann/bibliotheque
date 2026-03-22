---
name: no-rector-protected
enabled: true
event: bash
pattern: rector.*(vendor|migration|fixture)
action: block
---

**Rector forbidden on protected directories**

Never run Rector on `vendor/`, migrations or fixtures.
Only use Rector on application code (`src/`, `tests/`).
