#!/bin/bash
# UserPromptSubmit hook — warns the main Claude to invoke the session-handoff
# skill when the transcript size approaches the auto-compact threshold.
# Tuned for Opus 4.6 with the 1M context window (claude-opus-4-6[1m]).

INPUT=$(cat)
TRANSCRIPT=$(echo "$INPUT" | jq -r '.transcript_path // empty')

if [ -z "$TRANSCRIPT" ] || [ ! -f "$TRANSCRIPT" ]; then
  exit 0
fi

# Portable byte size (macOS + Linux)
SIZE=$(stat -f %z "$TRANSCRIPT" 2>/dev/null || stat -c %s "$TRANSCRIPT" 2>/dev/null || echo 0)

# Thresholds for 1M context. JSONL transcript ≈ 5–8 MB when full.
# SOFT ≈ 55% context, HARD ≈ 70% context — leaves room for the handoff itself.
SOFT=3145728   # 3 MB
HARD=4718592   # 4.5 MB

if [ "$SIZE" -ge "$HARD" ]; then
  MSG="CONTEXT CRITICAL ($(( SIZE / 1048576 )) MB transcript) — auto-compact imminent. BEFORE responding to the user, you MUST invoke the session-handoff skill via the Skill tool to save current work state, decisions, and next steps to .claude/session-handoff.md. Do not proceed with the user's request until the handoff is written. Once auto-compact fires, mid-conversation state will be lost."
elif [ "$SIZE" -ge "$SOFT" ]; then
  MSG="Context is getting large ($(( SIZE / 1048576 )) MB transcript). At the next natural checkpoint (end of current task), invoke the session-handoff skill to preserve state in .claude/session-handoff.md before auto-compact fires."
else
  exit 0
fi

jq -n --arg ctx "$MSG" '{hookSpecificOutput:{hookEventName:"UserPromptSubmit",additionalContext:$ctx}}'
