# Plan: #292 UX/UI interactions & micro-UX (medium-priority sub-issues)

## Context

Parent issue #292 has 6 sub-issues. 2A (bulk tome actions) is done. This plan covers the 3 MEDIUM sub-issues (#304, #305, #306) in a single branch since they all touch `ComicDetail.tsx` and are cohesive UX improvements.

LOW sub-issues (#303, #307) are deferred to separate work.

## Branch

`feat/292-interactions-micro-ux`

## Sub-issue 1: #304 — Toast undo instead of delete confirmation modal

**Files**: `ComicDetail.tsx`, `Home.tsx`, `CardActionBar.tsx`

**Changes**:
- Remove `ConfirmModal` usage for delete in both `ComicDetail.tsx` and `Home.tsx`
- Remove `showDelete`/`deleteTarget` state
- On delete click: immediately call `deleteComic.mutate()`, then show `toast('Série supprimée', { action: { label: 'Annuler', onClick: () => restore(id) }, duration: 5000 })`
- In `ComicDetail.tsx`: navigate to `/` immediately after delete, toast persists across navigation (Sonner default)
- In `Home.tsx`: optimistic removal from list, undo restores
- Use `useRestoreComic` (from `useTrash` hook or create standalone) for the undo action
- `ConfirmModal` component itself stays (may be used elsewhere)

**Test**: `ComicDetail.test.tsx`, `Home.test.tsx` — update delete tests to verify toast+undo instead of modal

## Sub-issue 2: #305 — Group tome toggle toasts

**Files**: `ComicDetail.tsx` (new `useDebouncedToast` hook or inline ref-based debounce)

**Changes**:
- In `handleToggleTome`: replace individual `toast.success(...)` with a debounced counter
- Use `useRef` to track pending success count + timeout ID
- On each successful toggle: increment counter, reset 1s timeout
- When timeout fires: show single toast `"${count} tome(s) mis à jour"` (or `"Tome X — ... activé"` if count=1)
- Keep individual `toast.error(...)` as-is
- `handleToggleAllTomes` already doesn't show per-tome success toasts — no change needed

**Test**: `ComicDetail.test.tsx` — verify grouped toast behavior

## Sub-issue 3: #306 — Lightbox cover on detail page

**Files**: `ComicDetail.tsx`, new `CoverLightbox.tsx` component

**Changes**:
- Add `cursor-pointer` + click handler on cover `<img>` in `ComicDetail.tsx`
- New `CoverLightbox` component: Headless UI `Dialog`, fullscreen dark overlay, centered `<img>` at max resolution, click/Escape to close
- Only render lightbox if cover exists (not placeholder)
- Add `aria-label` for accessibility

**Test**: `CoverLightbox.test.tsx`, update `ComicDetail.test.tsx`

## Implementation order

1. #306 (lightbox) — isolated, no dependencies
2. #305 (grouped toasts) — isolated change in handleToggleTome
3. #304 (toast undo) — needs useRestoreComic wiring, touches 2 pages

## Verification

- `ddev exec "cd frontend && npx vitest run"` — all tests pass
- `make lint-front` — no TypeScript errors
- Manual: delete from Home → toast with undo → click undo → series restored
- Manual: delete from ComicDetail → navigates to Home → toast with undo
- Manual: rapid-toggle 3 tomes → single grouped toast after 1s
- Manual: click cover → lightbox opens → Escape/click closes
