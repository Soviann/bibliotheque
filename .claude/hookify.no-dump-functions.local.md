---
name: no-dump-functions
enabled: true
event: file
pattern: \b(dd|dump|var_dump|print_r)\s*\(
action: block
---

**Debug functions are forbidden in committed code**

`dd()`, `dump()`, `var_dump()`, and `print_r()` must not be written to files.
Use proper logging (`$this->logger->debug()`) or assertions in tests instead.
