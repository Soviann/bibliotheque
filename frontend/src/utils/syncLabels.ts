export const operationLabels: Record<string, string> = {
  create: "Création",
  delete: "Suppression",
  update: "Mise à jour",
};

export const resourceLabels: Record<string, string> = {
  comic_series: "série",
  tome: "tome",
};

export const fieldLabels: Record<string, string> = {
  authors: "Auteurs",
  bought: "Acheté",
  coverUrl: "Couverture",
  description: "Description",
  downloaded: "Téléchargé",
  isbn: "ISBN",
  isOneShot: "One-shot",
  latestPublishedIssue: "Dernier tome paru",
  number: "Numéro",
  onNas: "NAS",
  publisher: "Éditeur",
  read: "Lu",
  status: "Statut",
  title: "Titre",
  tomeEnd: "Fin",
  tomes: "Tomes",
  type: "Type",
};

export function formatSyncValue(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "boolean") return value ? "Oui" : "Non";
  if (Array.isArray(value)) return `${value.length} élément(s)`;
  return String(value);
}
