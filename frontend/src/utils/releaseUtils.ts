import type { ComicSeries } from "../types/api";
import { ComicStatus } from "../types/enums";

const NEW_RELEASE_DAYS = 7;

/**
 * Vérifie si une série a de nouveaux tomes détectés récemment.
 *
 * Conditions :
 * - Statut BUYING
 * - latestPublishedIssueUpdatedAt ≤ 7 jours
 * - latestPublishedIssue > nombre max de tomes possédés
 */
export function hasNewRelease(comic: ComicSeries): boolean {
  if (comic.status !== ComicStatus.BUYING) return false;
  if (!comic.latestPublishedIssue || !comic.latestPublishedIssueUpdatedAt)
    return false;

  const updatedAt = new Date(comic.latestPublishedIssueUpdatedAt);
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - NEW_RELEASE_DAYS);

  if (updatedAt < cutoff) return false;

  const maxOwned = (comic.tomes ?? []).reduce(
    (max, t) => Math.max(max, t.number),
    0,
  );

  return comic.latestPublishedIssue > maxOwned;
}
