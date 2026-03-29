import { describe, expect, it } from "vitest";
import { createMockComicSeries } from "../../helpers/factories";
import { filterSeriesToBuy, formatTomeRanges, getNextTomesToBuy } from "../../../utils/toBuyUtils";

describe("getNextTomesToBuy", () => {
  it("returns empty array when no unbought tomes", () => {
    const series = createMockComicSeries({ unboughtTomes: [] });
    expect(getNextTomesToBuy(series)).toEqual([]);
  });

  it("returns unbought tomes sorted by number", () => {
    const series = createMockComicSeries({
      unboughtTomes: [
        { id: 30, isHorsSerie: false, number: 3 },
        { id: 20, isHorsSerie: false, number: 2 },
      ],
    });
    const result = getNextTomesToBuy(series);
    expect(result).toEqual([
      { id: 20, isHorsSerie: false, number: 2 },
      { id: 30, isHorsSerie: false, number: 3 },
    ]);
  });

  it("places hors-série after regular tomes", () => {
    const series = createMockComicSeries({
      unboughtTomes: [
        { id: 200, isHorsSerie: true, number: 1 },
        { id: 100, isHorsSerie: false, number: 1 },
      ],
    });
    const result = getNextTomesToBuy(series);
    expect(result[0].isHorsSerie).toBe(false);
    expect(result[1].isHorsSerie).toBe(true);
  });
});

describe("formatTomeRanges", () => {
  it("returns empty string for empty array", () => {
    expect(formatTomeRanges([])).toBe("");
  });

  it("formats a single tome", () => {
    expect(formatTomeRanges([5])).toBe("T.5");
  });

  it("formats consecutive tomes as a range", () => {
    expect(formatTomeRanges([1, 2, 3])).toBe("T.1-3");
  });

  it("formats mixed ranges and singles", () => {
    expect(formatTomeRanges([1, 2, 3, 5, 7, 8, 9])).toBe("T.1-3, T.5, T.7-9");
  });

  it("formats two consecutive tomes as a range", () => {
    expect(formatTomeRanges([4, 5])).toBe("T.4-5");
  });

  it("formats all singles", () => {
    expect(formatTomeRanges([1, 3, 5])).toBe("T.1, T.3, T.5");
  });
});

describe("filterSeriesToBuy", () => {
  it("includes buying series with unbought tomes", () => {
    const series = createMockComicSeries({
      status: "buying",
      unboughtTomes: [{ id: 1, isHorsSerie: false, number: 1 }],
    });
    expect(filterSeriesToBuy([series])).toEqual([series]);
  });

  it("excludes non-buying series", () => {
    const series = createMockComicSeries({
      status: "finished",
      unboughtTomes: [{ id: 1, isHorsSerie: false, number: 1 }],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes one-shots", () => {
    const series = createMockComicSeries({
      isOneShot: true,
      unboughtTomes: [{ id: 1, isHorsSerie: false, number: 1 }],
    });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });

  it("excludes series with all tomes bought", () => {
    const series = createMockComicSeries({ unboughtTomes: [] });
    expect(filterSeriesToBuy([series])).toEqual([]);
  });
});
