import type { Tome } from "../types/api";

/**
 * Compte le nombre de tomes couverts en tenant compte des plages (tomeEnd).
 * Un tome avec number=1 et tomeEnd=3 couvre 3 numéros.
 * Accepte un prédicat optionnel pour filtrer les tomes avant le comptage.
 */
export function countCoveredTomes(tomes: Tome[], predicate?: (t: Tome) => boolean): number {
  const filtered = predicate ? tomes.filter(predicate) : tomes;
  return filtered.reduce((sum, t) => sum + Math.max(1, (t.tomeEnd ?? t.number) - t.number + 1), 0);
}
