---
name: frontend-code-review
description: "Review frontend files (.tsx, .ts) against Bibliotheque conventions (React 19, Tailwind 4, TanStack Query)."
---

# Frontend Code Review

Review frontend code against the checklist below.

## Process

1. Read the target files (staged changes or named files)
2. Check each rule
3. Report using the template

## Rules (urgent unless noted)

### Code quality
- **Tailwind-first**: utility classes over custom CSS. No new CSS modules unless Tailwind can't express it.
- **`cn()` helper for conditional classes**: never ternaries or template strings in `className`.
- **Incoming `className` prop last**: `cn('base-classes', className)` so callers can override.
- **Alphabetical imports** grouped: react → libs → `@/...` → relative.
- **No `any`**: strict TypeScript. Use `unknown` + narrowing if truly unknown.
- **Lazy-loaded pages**: new pages added via `React.lazy()` in `App.tsx`.

### Performance
- **Memoize complex props**: objects/arrays/callbacks passed to child components → `useMemo`/`useCallback`.
- **TanStack Query**: mutations must `invalidateQueries` on success for affected keys. No manual refetch.
- **No inline object literals** as props to memoized children.

### Business logic (Bibliotheque-specific)
- **API calls via `apiFetch<T>()`**: never raw `fetch` (handles JWT, Content-Type, 401 redirects).
- **JSON-LD**: response shape uses `member` / `totalItems`, NOT `hydra:member`.
- **PATCH, not PUT**: for partial updates (PUT clears OneToMany collections silently). Use `application/merge-patch+json`.
- **Embedded entities**: serialize with `@id` (IRI), not `id` (integer).
- **Offline mutations**: use `useOfflineMutation` for CREATE/UPDATE/DELETE — integrates IndexedDB queue + optimistic updates.
- **Auth**: protected routes wrapped in `AuthGuard`. Token read from `localStorage` + mirrored to IndexedDB.
- **Dark mode**: `useDarkMode` hook (toggles `.dark` on `<html>`).

### Tests
- New component → companion `*.test.tsx` in `frontend/src/__tests__/`.
- Use RTL, Vitest, jsdom. Queries: `getByRole` > `getByText` > `getByTestId`.

## Output

### If issues found:
```
# Code review
Found <N> urgent issues:

## 1 <description>
FilePath: <path> line <line>
### Suggested fix
<fix>

---

Found <M> suggestions:
## 1 <description>
...
```

### If clean:
```
## Code review
No issues found.
```

If urgent issues require code changes, ask: "Want me to apply these fixes?"
