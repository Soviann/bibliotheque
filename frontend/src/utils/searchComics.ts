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

let cachedComics: ComicSeries[] | null = null;
let cachedFuse: Fuse<ComicSeries> | null = null;

/**
 * Recherche floue multi-champs (titre, auteurs, éditeur) via Fuse.js.
 * Retourne tous les comics si la query est vide.
 * L'index Fuse est mis en cache et recréé uniquement si la liste change.
 */
export function searchComics(
  comics: ComicSeries[],
  query: string,
): ComicSeries[] {
  const q = query.trim();
  if (!q) return comics;

  if (comics !== cachedComics) {
    cachedFuse = new Fuse(comics, fuseOptions);
    cachedComics = comics;
  }

  return cachedFuse!.search(q).map((result) => result.item);
}
