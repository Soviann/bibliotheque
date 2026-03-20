import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { ComicSeries } from "../../../types/api";
import { ComicStatus, ComicType } from "../../../types/enums";
import { hasNewRelease } from "../../../utils/releaseUtils";

function makeComic(overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": "/api/comic_series/1",
    amazonUrl: null,
    authors: [],
    boughtCount: 0,
    coveredCount: 0,
    coverImage: null,
    coverUrl: null,
    createdAt: "2024-01-01T00:00:00+00:00",
    defaultTomeBought: false,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: null,
    downloadedCount: 0,
    id: 1,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    latestPublishedIssueUpdatedAt: null,
    maxTomeNumber: null,
    publishedDate: null,
    publisher: null,
    readCount: 0,
    status: ComicStatus.BUYING,
    title: "Test",
    tomesCount: 0,
    type: ComicType.MANGA,
    unboughtTomeNumbers: [],
    updatedAt: "2024-01-01T00:00:00+00:00",
    ...overrides,
  };
}

function recentDate(daysAgo: number): string {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  return date.toISOString();
}

describe("hasNewRelease", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-03-14T12:00:00Z"));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("returns true when new tomes detected recently", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(3),
      maxTomeNumber: 3,
    });
    expect(hasNewRelease(comic)).toBe(true);
  });

  it("returns false when not BUYING status", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(3),
      maxTomeNumber: 2,
      status: ComicStatus.FINISHED,
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when update is older than 7 days", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(10),
      maxTomeNumber: 2,
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns false when all tomes owned", () => {
    const comic = makeComic({
      latestPublishedIssue: 3,
      latestPublishedIssueUpdatedAt: recentDate(1),
      maxTomeNumber: 3,
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
      maxTomeNumber: 1,
    });
    expect(hasNewRelease(comic)).toBe(false);
  });

  it("returns true with no tomes when issue detected recently", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(1),
      maxTomeNumber: null,
    });
    expect(hasNewRelease(comic)).toBe(true);
  });

  it("returns true on day 7 (boundary)", () => {
    const comic = makeComic({
      latestPublishedIssue: 5,
      latestPublishedIssueUpdatedAt: recentDate(7),
      maxTomeNumber: 1,
    });
    // At exactly 7 days, updatedAt will be within the cutoff window
    // (cutoff = now - 7 days, and updatedAt >= cutoff since same date)
    expect(hasNewRelease(comic)).toBe(true);
  });
});
