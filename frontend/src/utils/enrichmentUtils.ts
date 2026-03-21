export function formatEnrichmentValue(value: unknown, maxLength = 100): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "boolean") return value ? "Oui" : "Non";
  const str = String(value);
  return str.length > maxLength ? str.slice(0, maxLength) + "…" : str;
}
