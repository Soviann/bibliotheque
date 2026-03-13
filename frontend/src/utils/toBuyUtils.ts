import type { ComicSeries } from "../types/api";

export function getNextTomesToBuy(series: ComicSeries): number[] {
  return series.tomes
    .filter((t) => !t.bought)
    .map((t) => t.number)
    .sort((a, b) => a - b);
}

export function filterSeriesToBuy(series: ComicSeries[]): ComicSeries[] {
  return series.filter(
    (s) => s.status === "buying" && !s.isOneShot && s.tomes.some((t) => !t.bought),
  );
}
