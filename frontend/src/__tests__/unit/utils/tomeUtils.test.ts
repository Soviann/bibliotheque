import { describe, expect, it } from "vitest";
import type { Tome } from "../../../types/api";
import {
  countCoveredTomes,
  getTrailingMissingTomeNumbers,
} from "../../../utils/tomeUtils";
import { createMockTome } from "../../helpers/factories";

describe("countCoveredTomes", () => {
  it("returns 0 for empty array", () => {
    expect(countCoveredTomes([])).toBe(0);
  });

  it("counts 1 per tome when tomeEnd is null", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 2 }),
      createMockTome({ id: 3, number: 3 }),
    ];
    expect(countCoveredTomes(tomes)).toBe(3);
  });

  it("counts the range covered when tomeEnd is set", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, tomeEnd: 3 }), // covers 3
      createMockTome({ id: 2, number: 4, tomeEnd: 6 }), // covers 3
    ];
    expect(countCoveredTomes(tomes)).toBe(6);
  });

  it("handles mix of single tomes and ranges", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1 }), // covers 1
      createMockTome({ id: 2, number: 2, tomeEnd: 4 }), // covers 3
      createMockTome({ id: 3, number: 5 }), // covers 1
    ];
    expect(countCoveredTomes(tomes)).toBe(5);
  });

  it("filters by predicate when provided", () => {
    const tomes = [
      createMockTome({ bought: true, id: 1, number: 1, tomeEnd: 3 }), // covers 3
      createMockTome({ bought: false, id: 2, number: 4, tomeEnd: 6 }), // covers 3
      createMockTome({ bought: true, id: 3, number: 7 }), // covers 1
    ];
    expect(countCoveredTomes(tomes, (t) => t.bought)).toBe(4);
  });

  it("handles tomeEnd equal to number (single tome)", () => {
    const tomes = [createMockTome({ id: 1, number: 5, tomeEnd: 5 })];
    expect(countCoveredTomes(tomes)).toBe(1);
  });
});

describe("getTrailingMissingTomeNumbers", () => {
  it("returns [] when latestPublishedIssue is null", () => {
    expect(getTrailingMissingTomeNumbers([], null)).toEqual([]);
  });

  it("returns [] when latestPublishedIssue is undefined", () => {
    expect(getTrailingMissingTomeNumbers([], undefined)).toEqual([]);
  });

  it("returns [] when latestPublishedIssue is 0", () => {
    expect(getTrailingMissingTomeNumbers([], 0)).toEqual([]);
  });

  it("returns [1..published] for an empty collection", () => {
    expect(getTrailingMissingTomeNumbers([], 4)).toEqual([1, 2, 3, 4]);
  });

  it("returns the trailing range after the last contiguous tome", () => {
    const tomes: Tome[] = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 2 }),
      createMockTome({ id: 3, number: 3 }),
    ];
    expect(getTrailingMissingTomeNumbers(tomes, 5)).toEqual([4, 5]);
  });

  it("starts after tomeEnd when the last tome is a range", () => {
    const tomes: Tome[] = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 2, tomeEnd: 4 }),
    ];
    expect(getTrailingMissingTomeNumbers(tomes, 6)).toEqual([5, 6]);
  });

  it("ignores internal gaps when the last owned tome is past them", () => {
    const tomes: Tome[] = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 2 }),
      createMockTome({ id: 3, number: 5 }),
    ];
    expect(getTrailingMissingTomeNumbers(tomes, 7)).toEqual([6, 7]);
  });

  it("returns [] when the last owned tome already matches latestPublishedIssue", () => {
    const tomes: Tome[] = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 3 }),
    ];
    expect(getTrailingMissingTomeNumbers(tomes, 3)).toEqual([]);
  });

  it("returns [] when the last owned tome is past latestPublishedIssue", () => {
    const tomes: Tome[] = [createMockTome({ id: 1, number: 5 })];
    expect(getTrailingMissingTomeNumbers(tomes, 3)).toEqual([]);
  });

  it("ignores hors-série tomes for the 'last owned' calculation", () => {
    const tomes: Tome[] = [
      createMockTome({ id: 1, number: 1 }),
      createMockTome({ id: 2, number: 2 }),
      createMockTome({ id: 3, isHorsSerie: true, number: 99 }),
    ];
    expect(getTrailingMissingTomeNumbers(tomes, 4)).toEqual([3, 4]);
  });
});
