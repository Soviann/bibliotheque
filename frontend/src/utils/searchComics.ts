import Fuse, { type IFuseOptions } from "fuse.js";
import type { ComicSeries } from "../types/api";

const fuseOptions: IFuseOptions<ComicSeries> = {
  ignoreLocation: true,
  keys: [
    { name: "title", weight: 2 },
    { name: "authors.name", weight: 1.5 },
    { name: "publisher", weight: 1 },
  ],
  threshold: 0.35,
};

/**
 * Recherche floue multi-champs (titre, auteurs, éditeur) via Fuse.js.
 * Retourne tous les comics si la query est vide.
 */
export function searchComics(
  comics: ComicSeries[],
  query: string,
): ComicSeries[] {
  const q = query.trim();
  if (!q) return comics;

  const fuse = new Fuse(comics, fuseOptions);
  return fuse.search(q).map((result) => result.item);
}
