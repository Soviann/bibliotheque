import { ChevronDown, ChevronRight, History } from "lucide-react";
import { useState } from "react";
import { useEnrichmentLogs } from "../hooks/useEnrichment";
import {
  EnrichableFieldLabel,
  EnrichmentActionColor,
  EnrichmentActionLabel,
  type EnrichmentAction,
  EnrichmentConfidenceColor,
  EnrichmentConfidenceLabel,
  type EnrichmentConfidence,
} from "../types/enums";
import { formatEnrichmentValue } from "../utils/enrichmentUtils";

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    month: "short",
    year: "numeric",
  });
}

export default function EnrichmentHistory({ seriesId }: { seriesId: number }) {
  const [open, setOpen] = useState(false);
  const { data: logs } = useEnrichmentLogs(seriesId);

  if (!logs || logs.length === 0) return null;

  return (
    <div className="mt-6">
      <button
        className="flex items-center gap-2 text-sm font-medium text-text-secondary hover:text-text-primary"
        onClick={() => setOpen((prev) => !prev)}
        type="button"
      >
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        <History className="h-4 w-4" />
        Historique d&apos;enrichissement ({logs.length})
      </button>

      {open && (
        <div className="mt-3 space-y-2">
          {logs.map((log) => (
            <div
              className="flex flex-wrap items-start gap-2 rounded-lg border border-surface-border bg-surface-secondary p-3 text-sm"
              key={log.id}
            >
              <span className="text-xs text-text-tertiary">{formatDate(log.createdAt)}</span>
              <span
                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${EnrichmentActionColor[log.action as EnrichmentAction]}`}
              >
                {EnrichmentActionLabel[log.action as EnrichmentAction]}
              </span>
              <span className="font-medium text-text-primary">
                {EnrichableFieldLabel[log.field] ?? log.field}
              </span>
              <span
                className={`inline-flex rounded-full px-2 py-0.5 text-xs ${EnrichmentConfidenceColor[log.confidence as EnrichmentConfidence]}`}
              >
                {EnrichmentConfidenceLabel[log.confidence as EnrichmentConfidence]}
              </span>
              <span className="text-xs text-text-tertiary">{log.source}</span>
              {log.oldValue !== null && log.newValue !== null && (
                <div className="w-full text-xs text-text-secondary">
                  {formatEnrichmentValue(log.oldValue)} → {formatEnrichmentValue(log.newValue)}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
