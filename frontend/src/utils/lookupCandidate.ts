import type { LookupCandidate } from "../types/api";

/** Représente le badge de volume affiché sur une carte de résultat de recherche. */
export type VolumeChip = { kind: "oneshot" } | { kind: "count"; label: string };

/**
 * Extrait l'année (4 chiffres) depuis une date de publication quelconque.
 * Retourne null si aucune année n'est trouvée ou si la valeur est vide.
 */
export function candidateYear(publishedDate: string | null): string | null {
  if (!publishedDate) return null;
  const match = publishedDate.match(/\d{4}/);
  return match ? match[0] : null;
}

/**
 * Dérive le badge de volume (one-shot ou nombre de tomes) depuis un candidat de recherche.
 * Priorité : isOneShot → tomeEnd → latestPublishedIssue → tomeNumber.
 * Retourne null si aucune information de volume n'est disponible.
 */
export function candidateVolumeChip(c: LookupCandidate): VolumeChip | null {
  if (c.isOneShot === true) return { kind: "oneshot" };
  const count = c.tomeEnd ?? c.latestPublishedIssue ?? c.tomeNumber;
  if (count != null && count > 0) {
    return { kind: "count", label: `${count} tome${count > 1 ? "s" : ""}` };
  }
  return null;
}
