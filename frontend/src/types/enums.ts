export const ComicStatus = {
  BUYING: "buying",
  FINISHED: "finished",
  STOPPED: "stopped",
  WISHLIST: "wishlist",
} as const;

export type ComicStatus = (typeof ComicStatus)[keyof typeof ComicStatus];

export const ComicStatusLabel: Record<ComicStatus, string> = {
  [ComicStatus.BUYING]: "En cours d'achat",
  [ComicStatus.FINISHED]: "Terminé",
  [ComicStatus.STOPPED]: "Arrêté",
  [ComicStatus.WISHLIST]: "Liste de souhaits",
};

export const ComicType = {
  BD: "bd",
  COMICS: "comics",
  LIVRE: "livre",
  MANGA: "manga",
} as const;

export type ComicType = (typeof ComicType)[keyof typeof ComicType];

export const ComicTypeLabel: Record<ComicType, string> = {
  [ComicType.BD]: "BD",
  [ComicType.COMICS]: "Comics",
  [ComicType.LIVRE]: "Livre",
  [ComicType.MANGA]: "Manga",
};
