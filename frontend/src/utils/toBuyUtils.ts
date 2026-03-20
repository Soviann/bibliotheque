import type { ComicSeries } from "../types/api";

export function getNextTomesToBuy(series: ComicSeries): number[] {
  return [...series.unboughtTomeNumbers].sort((a, b) => a - b);
}

export function filterSeriesToBuy(series: ComicSeries[]): ComicSeries[] {
  return series.filter(
    (s) => s.status === "buying" && !s.isOneShot && s.unboughtTomeNumbers.length > 0,
  );
}
