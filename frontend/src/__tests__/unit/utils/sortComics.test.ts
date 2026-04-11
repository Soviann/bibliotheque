import { sortComics, type SortOption } from "../../../utils/sortComics";
import { createMockComicSeries } from "../../helpers/factories";

describe("sortComics", () => {
  it("sorts by title ascending by default", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Zelda" }),
      createMockComicSeries({ id: 2, title: "Astérix" }),
      createMockComicSeries({ id: 3, title: "Naruto" }),
    ];

    const result = sortComics(comics, "title-asc");

    expect(result.map((c) => c.title)).toEqual(["Astérix", "Naruto", "Zelda"]);
  });

  it("sorts by title descending", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Astérix" }),
      createMockComicSeries({ id: 2, title: "Zelda" }),
    ];

    const result = sortComics(comics, "title-desc");

    expect(result.map((c) => c.title)).toEqual(["Zelda", "Astérix"]);
  });

  it("sorts by createdAt descending (most recent first)", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        createdAt: "2024-01-01T00:00:00+00:00",
        title: "Old",
      }),
      createMockComicSeries({
        id: 2,
        createdAt: "2025-06-01T00:00:00+00:00",
        title: "New",
      }),
      createMockComicSeries({
        id: 3,
        createdAt: "2025-03-01T00:00:00+00:00",
        title: "Mid",
      }),
    ];

    const result = sortComics(comics, "createdAt-desc");

    expect(result.map((c) => c.title)).toEqual(["New", "Mid", "Old"]);
  });

  it("sorts by createdAt ascending (oldest first)", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        createdAt: "2025-06-01T00:00:00+00:00",
        title: "New",
      }),
      createMockComicSeries({
        id: 2,
        createdAt: "2024-01-01T00:00:00+00:00",
        title: "Old",
      }),
    ];

    const result = sortComics(comics, "createdAt-asc");

    expect(result.map((c) => c.title)).toEqual(["Old", "New"]);
  });

  it("sorts by tomes count descending", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Few", tomesCount: 1 }),
      createMockComicSeries({ id: 2, title: "Many", tomesCount: 3 }),
      createMockComicSeries({ id: 3, title: "None", tomesCount: 0 }),
    ];

    const result = sortComics(comics, "tomes-desc");

    expect(result.map((c) => c.title)).toEqual(["Many", "Few", "None"]);
  });

  it("sorts by tomes count ascending", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Many", tomesCount: 2 }),
      createMockComicSeries({ id: 2, title: "None", tomesCount: 0 }),
    ];

    const result = sortComics(comics, "tomes-asc");

    expect(result.map((c) => c.title)).toEqual(["None", "Many"]);
  });

  it("does not mutate the original array", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "B" }),
      createMockComicSeries({ id: 2, title: "A" }),
    ];
    const original = [...comics];

    sortComics(comics, "title-asc");

    expect(comics[0].title).toBe(original[0].title);
    expect(comics[1].title).toBe(original[1].title);
  });

  it("falls back to title-asc for unknown sort value", () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Zelda" }),
      createMockComicSeries({ id: 2, title: "Astérix" }),
    ];

    const result = sortComics(comics, "unknown" as SortOption);

    expect(result.map((c) => c.title)).toEqual(["Astérix", "Zelda"]);
  });

  it("returns empty array for empty input", () => {
    expect(sortComics([], "title-asc")).toEqual([]);
  });
});
