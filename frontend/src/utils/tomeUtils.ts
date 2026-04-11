import type { Tome } from "../types/api";

/**
 * Compte le nombre de tomes couverts en tenant compte des plages (tomeEnd).
 * Un tome avec number=1 et tomeEnd=3 couvre 3 numéros.
 * Accepte un prédicat optionnel pour filtrer les tomes avant le comptage.
 */
export function countCoveredTomes(
  tomes: Tome[],
  predicate?: (t: Tome) => boolean,
): number {
  const filtered = predicate ? tomes.filter(predicate) : tomes;
  return filtered.reduce(
    (sum, t) => sum + Math.max(1, (t.tomeEnd ?? t.number) - t.number + 1),
    0,
  );
}

/**
 * Retourne la liste des numéros de tomes manquants après le dernier tome
 * non hors-série présent et jusqu'à latestPublishedIssue inclus. Les trous
 * internes ne sont pas couverts : ce helper sert à "rattraper" les parutions,
 * pas à combler une collection incomplète.
 */
export function getTrailingMissingTomeNumbers(
  tomes: Tome[],
  latestPublishedIssue: null | number | undefined,
): number[] {
  if (!latestPublishedIssue || latestPublishedIssue <= 0) return [];
  const lastOwned = tomes
    .filter((t) => !t.isHorsSerie)
    .reduce((max, t) => Math.max(max, t.tomeEnd ?? t.number), 0);
  if (lastOwned >= latestPublishedIssue) return [];
  const missing: number[] = [];
  for (let n = lastOwned + 1; n <= latestPublishedIssue; n++) {
    missing.push(n);
  }
  return missing;
}
