---
name: require-ddev-exec
enabled: false
event: bash
action: block
conditions:
  - field: command
    operator: regex_match
    pattern: ^(?!git\s)(?!sshpass\s)[\s\S]*(?:\bbin/(?:phpunit|console)\b|\bvendor/bin/|\bcomposer\s|\bnpm\s|\bnpx\s)
  - field: command
    operator: not_contains
    pattern: ddev exec
---

**Command outside DDEV container detected!**

`bin/console`, `bin/phpunit`, `vendor/bin/*`, `composer`, `npm` and `npx` must be run inside the DDEV container.

**Use:**
- `ddev exec bin/phpunit ...`
- `ddev exec bin/console ...`
- `ddev exec composer ...`
- `ddev exec npm ...` / `ddev exec npx ...`
- Or Makefile targets: `make test`, `make lint`, etc.
