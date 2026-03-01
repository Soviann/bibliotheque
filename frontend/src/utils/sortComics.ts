import type { ComicSeries } from "../types/api";

export type SortOption = "createdAt-asc" | "createdAt-desc" | "title-asc" | "title-desc" | "tomes-asc" | "tomes-desc";

export function sortComics(comics: ComicSeries[], sort: SortOption): ComicSeries[] {
  const sorted = [...comics];
  switch (sort) {
    case "title-desc":
      return sorted.sort((a, b) => b.title.localeCompare(a.title, "fr"));
    case "createdAt-desc":
      return sorted.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    case "createdAt-asc":
      return sorted.sort((a, b) => a.createdAt.localeCompare(b.createdAt));
    case "tomes-desc":
      return sorted.sort((a, b) => (b.tomes?.length ?? 0) - (a.tomes?.length ?? 0));
    case "tomes-asc":
      return sorted.sort((a, b) => (a.tomes?.length ?? 0) - (b.tomes?.length ?? 0));
    default: // title-asc
      return sorted.sort((a, b) => a.title.localeCompare(b.title, "fr"));
  }
}
