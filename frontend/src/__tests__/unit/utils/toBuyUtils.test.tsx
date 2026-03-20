import type { ComicSeries } from "../../../types/api";
import { filterSeriesToBuy, getNextTomesToBuy } from "../../../utils/toBuyUtils";

function makeSeries(overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": "/api/comics/1",
    authors: [],
    boughtCount: 0,
    coveredCount: 0,
    coverImage: null,
    coverUrl: null,
    createdAt: "2024-01-01T00:00:00+00:00",
    defaultTomeBought: true,
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
    status: "buying",
    title: "Test Series",
    tomesCount: 0,
    type: "manga",
    unboughtTomeNumbers: [],
    updatedAt: "2024-01-01T00:00:00+00:00",
    ...overrides,
  };
}

describe("getNextTomesToBuy", () => {
  it("returns empty array when all tomes are bought", () => {
    const series = makeSeries({ unboughtTomeNumbers: [] });
    expect(getNextTomesToBuy(series)).toEqual([]);
  });

  it("returns unbought tome numbers sorted", () => {
    const series = makeSeries({ unboughtTomeNumbers: [3, 2] });
    expect(getNextTomesToBuy(series)).toEqual([2, 3]);
  });

  it("returns empty array when series has no tomes", () => {
    const series = makeSeries({ unboughtTomeNumbers: [] });
    expect(getNextTomesToBuy(series)).toEqual([]);
  });
});

describe("filterSeriesToBuy", () => {
  it("includes buying series with unbought tomes", () => {
    const series = makeSeries({
      status: "buying",
      unboughtTomeNumbers: [1],
    });
    expect(filterSeriesToBuy([series])).toEqual([series]);
  });

  it("excludes non-buying series", () => {
    const series = makeSeries({
      status: "finished",
      unboughtTomeNumbers: [1],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes one-shots", () => {
    const series = makeSeries({
      isOneShot: true,
      unboughtTomeNumbers: [1],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes series with all tomes bought", () => {
    const series = makeSeries({ unboughtTomeNumbers: [] });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes series with no tomes", () => {
    const series = makeSeries({ unboughtTomeNumbers: [] });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });
});
