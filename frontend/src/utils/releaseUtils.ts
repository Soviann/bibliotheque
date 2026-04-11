import type { ComicSeries } from "../types/api";
import { ComicStatus } from "../types/enums";

const NEW_RELEASE_DAYS = 7;

const ACTIVE_ACQUISITION_STATUSES: ReadonlySet<ComicStatus> = new Set([
  ComicStatus.BUYING,
  ComicStatus.DOWNLOADING,
]);

/**
 * Vérifie si une série a de nouveaux tomes détectés récemment.
 *
 * Conditions :
 * - Statut actif (BUYING ou DOWNLOADING)
 * - latestPublishedIssueUpdatedAt ≤ 7 jours
 * - latestPublishedIssue > nombre max de tomes possédés
 */
export function hasNewRelease(comic: ComicSeries): boolean {
  if (!ACTIVE_ACQUISITION_STATUSES.has(comic.status)) return false;
  if (!comic.latestPublishedIssue || !comic.latestPublishedIssueUpdatedAt)
    return false;

  const updatedAt = new Date(comic.latestPublishedIssueUpdatedAt);
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - NEW_RELEASE_DAYS);

  if (updatedAt < cutoff) return false;

  return comic.latestPublishedIssue > (comic.maxTomeNumber ?? 0);
}
