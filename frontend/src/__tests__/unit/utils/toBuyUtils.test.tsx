import type { ComicSeries } from "../../../types/api";
import { filterSeriesToBuy, getNextTomesToBuy } from "../../../utils/toBuyUtils";

function makeSeries(overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": "/api/comics/1",
    authors: [],
    coverImage: null,
    coverUrl: null,
    createdAt: "2024-01-01T00:00:00+00:00",
    defaultTomeBought: true,
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
    status: "buying",
    title: "Test Series",
    tomes: [],
    type: "manga",
    updatedAt: "2024-01-01T00:00:00+00:00",
    ...overrides,
  };
}

describe("getNextTomesToBuy", () => {
  it("returns empty array when all tomes are bought", () => {
    const series = makeSeries({
      tomes: [
        { "@id": "/api/tomes/1", bought: true, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
        { "@id": "/api/tomes/2", bought: true, createdAt: "", downloaded: false, id: 2, isbn: null, number: 2, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(getNextTomesToBuy(series)).toEqual([]);
  });

  it("returns unbought tome numbers sorted", () => {
    const series = makeSeries({
      tomes: [
        { "@id": "/api/tomes/1", bought: true, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
        { "@id": "/api/tomes/2", bought: false, createdAt: "", downloaded: false, id: 2, isbn: null, number: 3, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
        { "@id": "/api/tomes/3", bought: false, createdAt: "", downloaded: false, id: 3, isbn: null, number: 2, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(getNextTomesToBuy(series)).toEqual([2, 3]);
  });

  it("returns empty array when series has no tomes", () => {
    const series = makeSeries({ tomes: [] });
    expect(getNextTomesToBuy(series)).toEqual([]);
  });
});

describe("filterSeriesToBuy", () => {
  it("includes buying series with unbought tomes", () => {
    const series = makeSeries({
      status: "buying",
      tomes: [
        { "@id": "/api/tomes/1", bought: false, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(filterSeriesToBuy([series])).toEqual([series]);
  });

  it("excludes non-buying series", () => {
    const series = makeSeries({
      status: "finished",
      tomes: [
        { "@id": "/api/tomes/1", bought: false, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes one-shots", () => {
    const series = makeSeries({
      isOneShot: true,
      tomes: [
        { "@id": "/api/tomes/1", bought: false, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes series with all tomes bought", () => {
    const series = makeSeries({
      tomes: [
        { "@id": "/api/tomes/1", bought: true, createdAt: "", downloaded: false, id: 1, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null, updatedAt: "" },
      ],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes series with no tomes", () => {
    const series = makeSeries({ tomes: [] });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });
});
