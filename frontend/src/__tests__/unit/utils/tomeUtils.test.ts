import { describe, expect, it } from "vitest";
import type { Tome } from "../../../types/api";
import { countCoveredTomes } from "../../../utils/tomeUtils";
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
      createMockTome({ id: 1, number: 1 }),              // covers 1
      createMockTome({ id: 2, number: 2, tomeEnd: 4 }),  // covers 3
      createMockTome({ id: 3, number: 5 }),              // covers 1
    ];
    expect(countCoveredTomes(tomes)).toBe(5);
  });

  it("filters by predicate when provided", () => {
    const tomes = [
      createMockTome({ bought: true, id: 1, number: 1, tomeEnd: 3 }),  // covers 3
      createMockTome({ bought: false, id: 2, number: 4, tomeEnd: 6 }), // covers 3
      createMockTome({ bought: true, id: 3, number: 7 }),              // covers 1
    ];
    expect(countCoveredTomes(tomes, (t) => t.bought)).toBe(4);
  });

  it("handles tomeEnd equal to number (single tome)", () => {
    const tomes = [
      createMockTome({ id: 1, number: 5, tomeEnd: 5 }),
    ];
    expect(countCoveredTomes(tomes)).toBe(1);
  });
});
