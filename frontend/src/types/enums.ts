export const ComicStatus = {
  BUYING: "buying",
  FINISHED: "finished",
  STOPPED: "stopped",
  WISHLIST: "wishlist",
} as const;

export type ComicStatus = (typeof ComicStatus)[keyof typeof ComicStatus];

export const ComicStatusColor: Record<ComicStatus, string> = {
  [ComicStatus.BUYING]: "bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400",
  [ComicStatus.FINISHED]: "bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400",
  [ComicStatus.STOPPED]: "bg-orange-100 text-orange-700 dark:bg-orange-950/30 dark:text-orange-400",
  [ComicStatus.WISHLIST]: "bg-violet-100 text-violet-700 dark:bg-violet-950/30 dark:text-violet-400",
};

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

export const ComicTypePlaceholder: Record<ComicType, string> = {
  [ComicType.BD]: "/placeholder-bd.jpg",
  [ComicType.COMICS]: "/placeholder-comics.jpg",
  [ComicType.LIVRE]: "/placeholder-livre.jpg",
  [ComicType.MANGA]: "/placeholder-manga.jpg",
};

export interface SelectOption {
  label: string;
  value: string;
}

export const typeOptions: SelectOption[] = Object.entries(ComicType).map(
  ([, value]) => ({
    label: ComicTypeLabel[value],
    value,
  }),
);

export const typeOptionsAll: SelectOption[] = [
  { label: "Tous les types", value: "" },
  ...typeOptions,
];

export const statusOptions: SelectOption[] = Object.entries(ComicStatus)
  .map(([, value]) => ({
    label: ComicStatusLabel[value],
    value,
  }))
  .sort((a, b) => a.label.localeCompare(b.label));

export const statusOptionsAll: SelectOption[] = [
  { label: "Tous les statuts", value: "" },
  ...statusOptions,
];
