---
name: no-co-authored-by
enabled: true
event: bash
pattern: Co-Authored-By
action: block
---

**Co-Authored-By interdit**

Le CLAUDE.md du projet indique explicitement : « No Co-Authored-By ».
Retire le trailer `Co-Authored-By` du message de commit.
