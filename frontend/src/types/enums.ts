export const ComicStatus = {
  BUYING: "buying",
  COMPLETE: "complete",
  DROPPED: "dropped",
  PAUSED: "paused",
  WISHLIST: "wishlist",
} as const;

export type ComicStatus = (typeof ComicStatus)[keyof typeof ComicStatus];

export const ComicStatusLabel: Record<ComicStatus, string> = {
  [ComicStatus.BUYING]: "En cours d'achat",
  [ComicStatus.COMPLETE]: "Complet",
  [ComicStatus.DROPPED]: "Abandonné",
  [ComicStatus.PAUSED]: "En pause",
  [ComicStatus.WISHLIST]: "Liste de souhaits",
};

export const ComicType = {
  BD: "bd",
  COMICS: "comics",
  MANGA: "manga",
  NOVEL: "novel",
  WEBTOON: "webtoon",
} as const;

export type ComicType = (typeof ComicType)[keyof typeof ComicType];

export const ComicTypeLabel: Record<ComicType, string> = {
  [ComicType.BD]: "BD",
  [ComicType.COMICS]: "Comics",
  [ComicType.MANGA]: "Manga",
  [ComicType.NOVEL]: "Roman",
  [ComicType.WEBTOON]: "Webtoon",
};
