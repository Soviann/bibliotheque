import { createMockAuthor, createMockComicSeries } from "../../helpers/factories";
import { searchComics } from "../../../utils/searchComics";

describe("searchComics", () => {
  const comics = [
    createMockComicSeries({
      authors: [createMockAuthor({ name: "Naoki Urasawa" })],
      id: 1,
      publisher: "Kana",
      title: "Monster",
    }),
    createMockComicSeries({
      authors: [createMockAuthor({ name: "Eiichiro Oda" })],
      id: 2,
      publisher: "Glénat",
      title: "One Piece",
    }),
    createMockComicSeries({
      authors: [
        createMockAuthor({ name: "Tsugumi Ohba" }),
        createMockAuthor({ name: "Takeshi Obata" }),
      ],
      id: 3,
      publisher: "Kana",
      title: "Death Note",
    }),
  ];

  it("returns all comics when query is empty", () => {
    expect(searchComics(comics, "")).toHaveLength(3);
    expect(searchComics(comics, "  ")).toHaveLength(3);
  });

  it("matches on title", () => {
    const results = searchComics(comics, "Monster");
    expect(results).toHaveLength(1);
    expect(results[0].title).toBe("Monster");
  });

  it("matches on author name", () => {
    const results = searchComics(comics, "Urasawa");
    expect(results).toHaveLength(1);
    expect(results[0].title).toBe("Monster");
  });

  it("matches on publisher", () => {
    const results = searchComics(comics, "Kana");
    expect(results).toHaveLength(2);
    const titles = results.map((c) => c.title).sort();
    expect(titles).toEqual(["Death Note", "Monster"]);
  });

  it("is case insensitive", () => {
    const results = searchComics(comics, "urasawa");
    expect(results).toHaveLength(1);
    expect(results[0].title).toBe("Monster");
  });

  it("trims whitespace", () => {
    const results = searchComics(comics, "  Monster  ");
    expect(results).toHaveLength(1);
    expect(results[0].title).toBe("Monster");
  });

  it("matches with typos (fuzzy)", () => {
    const results = searchComics(comics, "Uraswa");
    expect(results.length).toBeGreaterThanOrEqual(1);
    expect(results[0].title).toBe("Monster");
  });

  it("matches partial author name with typo", () => {
    const results = searchComics(comics, "Eiichro");
    expect(results.length).toBeGreaterThanOrEqual(1);
    expect(results[0].title).toBe("One Piece");
  });

  it("matches when comic has multiple authors", () => {
    const results = searchComics(comics, "Obata");
    expect(results).toHaveLength(1);
    expect(results[0].title).toBe("Death Note");
  });

  it("returns empty array when nothing matches", () => {
    const results = searchComics(comics, "zzzzzzzzz");
    expect(results).toHaveLength(0);
  });

  it("handles empty comics array", () => {
    expect(searchComics([], "test")).toHaveLength(0);
  });
});
