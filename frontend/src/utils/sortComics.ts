import type { ComicSeries } from "../types/api";

export function sortComics(comics: ComicSeries[], sort: string): ComicSeries[] {
  const sorted = [...comics];
  switch (sort) {
    case "title-desc":
      return sorted.sort((a, b) => b.title.localeCompare(a.title));
    case "createdAt-desc":
      return sorted.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    case "createdAt-asc":
      return sorted.sort((a, b) => a.createdAt.localeCompare(b.createdAt));
    case "tomes-desc":
      return sorted.sort((a, b) => (b.tomes?.length ?? 0) - (a.tomes?.length ?? 0));
    case "tomes-asc":
      return sorted.sort((a, b) => (a.tomes?.length ?? 0) - (b.tomes?.length ?? 0));
    default: // title-asc
      return sorted.sort((a, b) => a.title.localeCompare(b.title));
  }
}
