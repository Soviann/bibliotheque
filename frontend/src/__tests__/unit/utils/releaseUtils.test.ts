import { describe, expect, it } from "vitest";
import type { ComicSeries } from "../../../types/api";
import { ComicStatus, ComicType } from "../../../types/enums";
import { hasNewRelease } from "../../../utils/releaseUtils";

function makeComic(overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": "/api/comic_series/1",
    amazonUrl: null,
    authors: [],
    coverImage: null,
    coverUrl: null,
    createdAt: "2024-01-01T00:00:00+00:00",
    defaultTomeBought: false,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: null,
    id: 1,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    latestPublishedIssueUpdatedAt: null,
    publishedDate: null,
    publisher: null,
    status: ComicStatus.BUYING,
    title: "Test",
    tomes: [],
    type: ComicType.MANGA,
    updatedAt: "2024-01-01T00:00:00+00:00",
    ...overrides,
  };
}

function recentDate(daysAgo: number): string {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  return date.toISOString();
}

function makeTome(number: number) {
  return {
    "@id": `/api/tomes/${number}`,
    bought: true,
    createdAt: "2024-01-01T00:00:00+00:00",
    downloaded: false,
    id: number,
    isbn: null,
    number,
    onNas: false,
    read: false,
    title: null,
    tomeEnd: null,
    updatedAt: "2024-01-01T00:00:00+00:00",
  };
}

describe("hasNewRelease", () => {
  it("returns true when new tomes detected recently", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(3),
      tomes: [makeTome(1), makeTome(2), makeTome(3)],
    });
    expect(hasNewRelease(comic)).toBe(true);
  });

  it("returns false when not BUYING status", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(3),
      status: ComicStatus.FINISHED,
      tomes: [makeTome(1), makeTome(2)],
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when update is older than 7 days", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(10),
      tomes: [makeTome(1), makeTome(2)],
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when all tomes owned", () => {
    const comic = makeComic({
      latestPublishedIssue: 3,
      latestPublishedIssueUpdatedAt: recentDate(1),
      tomes: [makeTome(1), makeTome(2), makeTome(3)],
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when no latestPublishedIssue", () => {
    const comic = makeComic({
      latestPublishedIssue: null,
      latestPublishedIssueUpdatedAt: recentDate(1),
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when no latestPublishedIssueUpdatedAt", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: null,
      tomes: [makeTome(1)],
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns true with no tomes when issue detected recently", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(1),
      tomes: [],
    });
    expect(hasNewRelease(comic)).toBe(true);
  });

  it("returns true on day 7 (boundary)", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(7),
      tomes: [makeTome(1)],
    });
    // At exactly 7 days, updatedAt will be within the cutoff window
    // (cutoff = now - 7 days, and updatedAt >= cutoff since same date)
    expect(hasNewRelease(comic)).toBe(true);
  });
});
