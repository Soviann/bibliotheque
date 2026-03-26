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

export const ComicStatusShortLabel: Record<ComicStatus, string> = {
  [ComicStatus.BUYING]: "En cours",
  [ComicStatus.FINISHED]: "Terminé",
  [ComicStatus.STOPPED]: "Arrêté",
  [ComicStatus.WISHLIST]: "Souhaits",
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

export const EnrichmentConfidence = {
  HIGH: "high",
  LOW: "low",
  MEDIUM: "medium",
} as const;
export type EnrichmentConfidence =
  (typeof EnrichmentConfidence)[keyof typeof EnrichmentConfidence];

export const EnrichmentConfidenceLabel: Record<EnrichmentConfidence, string> = {
  [EnrichmentConfidence.HIGH]: "Haute",
  [EnrichmentConfidence.LOW]: "Basse",
  [EnrichmentConfidence.MEDIUM]: "Moyenne",
};

export const EnrichmentConfidenceColor: Record<EnrichmentConfidence, string> = {
  [EnrichmentConfidence.HIGH]:
    "bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400",
  [EnrichmentConfidence.LOW]:
    "bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-400",
  [EnrichmentConfidence.MEDIUM]:
    "bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400",
};

export const EnrichableFieldLabel: Record<string, string> = {
  amazonUrl: "Lien Amazon",
  authors: "Auteurs",
  cover: "Couverture",
  description: "Description",
  isbn: "ISBN",
  isOneShot: "One-shot",
  latestPublishedIssue: "Dernier tome paru",
  publisher: "Éditeur",
};

export const ProposalStatus = {
  ACCEPTED: "accepted",
  PENDING: "pending",
  PRE_ACCEPTED: "pre_accepted",
  REJECTED: "rejected",
  SKIPPED: "skipped",
} as const;
export type ProposalStatus =
  (typeof ProposalStatus)[keyof typeof ProposalStatus];

export const ProposalStatusLabel: Record<ProposalStatus, string> = {
  [ProposalStatus.ACCEPTED]: "Accepté",
  [ProposalStatus.PENDING]: "En attente",
  [ProposalStatus.PRE_ACCEPTED]: "Pré-approuvé",
  [ProposalStatus.REJECTED]: "Rejeté",
  [ProposalStatus.SKIPPED]: "Ignoré",
};

export const ProposalStatusColor: Record<ProposalStatus, string> = {
  [ProposalStatus.ACCEPTED]:
    "bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400",
  [ProposalStatus.PENDING]:
    "bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400",
  [ProposalStatus.PRE_ACCEPTED]:
    "bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400",
  [ProposalStatus.REJECTED]:
    "bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-400",
  [ProposalStatus.SKIPPED]:
    "bg-gray-100 text-gray-700 dark:bg-gray-950/30 dark:text-gray-400",
};

export const NotificationChannel = {
  BOTH: "both",
  IN_APP: "in_app",
  OFF: "off",
  PUSH: "push",
} as const;
export type NotificationChannel =
  (typeof NotificationChannel)[keyof typeof NotificationChannel];

export const NotificationChannelLabel: Record<NotificationChannel, string> = {
  [NotificationChannel.BOTH]: "In-app + Push",
  [NotificationChannel.IN_APP]: "In-app",
  [NotificationChannel.OFF]: "Désactivé",
  [NotificationChannel.PUSH]: "Push",
};

export const NotificationEntityType = {
  AUTHOR: "author",
  COMIC_SERIES: "comic_series",
  ENRICHMENT_PROPOSAL: "enrichment_proposal",
} as const;
export type NotificationEntityType =
  (typeof NotificationEntityType)[keyof typeof NotificationEntityType];

export const NotificationType = {
  AUTHOR_NEW_SERIES: "author_new_series",
  ENRICHMENT_APPLIED: "enrichment_applied",
  ENRICHMENT_REVIEW: "enrichment_review",
  MISSING_TOME: "missing_tome",
  NEW_RELEASE: "new_release",
} as const;
export type NotificationType =
  (typeof NotificationType)[keyof typeof NotificationType];

export const NotificationTypeLabel: Record<NotificationType, string> = {
  [NotificationType.AUTHOR_NEW_SERIES]: "Nouvelle série d'un auteur suivi",
  [NotificationType.ENRICHMENT_APPLIED]: "Enrichissement auto-appliqué",
  [NotificationType.ENRICHMENT_REVIEW]: "Enrichissement à valider",
  [NotificationType.MISSING_TOME]: "Tome manquant détecté",
  [NotificationType.NEW_RELEASE]: "Nouvelle parution",
};

export const SuggestionStatus = {
  ADDED: "added",
  DISMISSED: "dismissed",
  PENDING: "pending",
} as const;
export type SuggestionStatus =
  (typeof SuggestionStatus)[keyof typeof SuggestionStatus];
