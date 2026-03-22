---
name: no-schema-update
enabled: true
event: bash
action: block
conditions:
  - field: command
    operator: regex_match
    pattern: \bdoctrine:schema:update\b
  - field: command
    operator: regex_match
    pattern: ^(ddev|make|php|bin/)
---

**doctrine:schema:update is forbidden**

Always use migrations instead of direct schema updates.
Run `make migration` or `ddev exec bin/console doctrine:migrations:diff -n` to generate a migration.
