import type { ComicSeries } from "../types/api";

type UnboughtTome = ComicSeries["unboughtTomes"][number];

export function getNextTomesToBuy(series: ComicSeries): UnboughtTome[] {
  return [...series.unboughtTomes].sort((a, b) => {
    if (a.isHorsSerie !== b.isHorsSerie) return a.isHorsSerie ? 1 : -1;
    return a.number - b.number;
  });
}

/**
 * Formate une liste de numéros de tomes en tranches lisibles.
 * Ex: [1,2,3,5,7,8,9] → "T.1-3, T.5, T.7-9"
 */
export function formatTomeRanges(numbers: number[]): string {
  if (numbers.length === 0) return "";

  const ranges: string[] = [];
  let start = numbers[0];
  let end = numbers[0];

  for (let i = 1; i < numbers.length; i++) {
    if (numbers[i] === end + 1) {
      end = numbers[i];
    } else {
      ranges.push(start === end ? `T.${start}` : `T.${start}-${end}`);
      start = numbers[i];
      end = numbers[i];
    }
  }
  ranges.push(start === end ? `T.${start}` : `T.${start}-${end}`);

  return ranges.join(", ");
}

export function filterSeriesToBuy(series: ComicSeries[]): ComicSeries[] {
  return series.filter(
    (s) => s.status === "buying" && !s.isOneShot && s.unboughtTomes.length > 0,
  );
}

export function filterSeriesToDownload(series: ComicSeries[]): ComicSeries[] {
  return series.filter(
    (s) =>
      s.status === "downloading" && !s.isOneShot && s.unboughtTomes.length > 0,
  );
}
