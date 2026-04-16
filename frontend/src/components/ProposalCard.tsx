import { Check, X } from "lucide-react";
import { useState } from "react";
import type { EnrichmentProposal } from "../types/api";
import {
  ComicTypePlaceholder,
  EnrichableFieldLabel,
  EnrichmentConfidenceColor,
  EnrichmentConfidenceLabel,
  type EnrichmentConfidence,
  ProposalStatusColor,
  ProposalStatusLabel,
  type ProposalStatus,
} from "../types/enums";
import { formatEnrichmentValue } from "../utils/enrichmentUtils";
import CoverImage from "./CoverImage";

const TriggeredByLabel: Record<string, string> = {
  "command:auto-enrich": "auto-enrich",
  "event:create": "création",
  "event:update": "mise à jour",
};

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    month: "short",
    year: "numeric",
  });
}

export default function ProposalCard({
  onAccept,
  onReject,
  proposal,
  readonly = false,
}: {
  onAccept?: (id: number) => void;
  onReject?: (id: number) => void;
  proposal: EnrichmentProposal;
  readonly?: boolean;
}) {
  const [expanded, setExpanded] = useState(false);

  const renderValues = () => {
    if (proposal.field === "cover") {
      const placeholder = ComicTypePlaceholder[proposal.comicSeries.type];
      const currentSrc =
        typeof proposal.currentValue === "string" && proposal.currentValue
          ? proposal.currentValue
          : placeholder;
      const proposedSrc =
        typeof proposal.proposedValue === "string" && proposal.proposedValue
          ? proposal.proposedValue
          : placeholder;
      return (
        <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
          <CoverImage
            alt="Couverture actuelle"
            className="w-48 rounded-lg shadow-sm"
            fallbackSrc={placeholder}
            loading="lazy"
            objectFit="contain"
            src={currentSrc}
          />
          <span className="text-text-tertiary">→</span>
          <CoverImage
            alt="Couverture proposée"
            className="w-48 rounded-lg shadow-sm"
            fallbackSrc={placeholder}
            loading="lazy"
            objectFit="contain"
            src={proposedSrc}
          />
        </div>
      );
    }

    if (proposal.field === "description") {
      const currentText = expanded
        ? String(proposal.currentValue ?? "—")
        : formatEnrichmentValue(proposal.currentValue);
      const proposedText = expanded
        ? String(proposal.proposedValue ?? "—")
        : formatEnrichmentValue(proposal.proposedValue);
      return (
        <div
          className={`mt-1 text-sm text-text-secondary ${expanded ? "whitespace-pre-wrap" : "break-all"}`}
        >
          <span
            className="cursor-pointer text-text-tertiary hover:text-text-primary"
            onClick={() => setExpanded((v) => !v)}
            title={
              expanded
                ? "Cliquer pour réduire"
                : "Cliquer pour afficher la description complète"
            }
          >
            {currentText}
          </span>
          {" → "}
          <span
            className="cursor-pointer font-medium text-text-primary hover:text-text-primary"
            onClick={() => setExpanded((v) => !v)}
            title={
              expanded
                ? "Cliquer pour réduire"
                : "Cliquer pour afficher la description complète"
            }
          >
            {proposedText}
          </span>
        </div>
      );
    }

    return (
      <div className="mt-1 break-all text-sm text-text-secondary">
        <span className="text-text-tertiary">
          {formatEnrichmentValue(proposal.currentValue)}
        </span>
        {" → "}
        <span className="font-medium text-text-primary">
          {formatEnrichmentValue(proposal.proposedValue)}
        </span>
      </div>
    );
  };

  return (
    <div className="flex items-start justify-between gap-3 rounded-lg border border-surface-border bg-surface-secondary p-3">
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-medium text-text-primary">
            {EnrichableFieldLabel[proposal.field] ?? proposal.field}
          </span>
          <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${EnrichmentConfidenceColor[proposal.confidence as EnrichmentConfidence]}`}
          >
            {
              EnrichmentConfidenceLabel[
                proposal.confidence as EnrichmentConfidence
              ]
            }
          </span>
          <span className="text-xs text-text-tertiary">{proposal.source}</span>
          {proposal.triggeredBy && (
            <span className="text-xs text-text-tertiary">
              via{" "}
              {TriggeredByLabel[proposal.triggeredBy] ?? proposal.triggeredBy}
            </span>
          )}
          <span className="text-xs text-text-tertiary">
            {formatDate(proposal.createdAt)}
          </span>
          {readonly && (
            <>
              <span
                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${ProposalStatusColor[proposal.status as ProposalStatus]}`}
              >
                {ProposalStatusLabel[proposal.status as ProposalStatus]}
              </span>
              {proposal.reviewedAt && (
                <span className="text-xs text-text-tertiary">
                  {formatDate(proposal.reviewedAt)}
                </span>
              )}
            </>
          )}
        </div>
        {renderValues()}
      </div>
      {!readonly && onAccept && onReject && (
        <div className="flex shrink-0 gap-1">
          <button
            className="rounded-md bg-green-600 p-1.5 text-white hover:bg-green-700"
            onClick={() => onAccept(proposal.id)}
            title="Accepter"
            type="button"
          >
            <Check className="h-4 w-4" />
          </button>
          <button
            className="rounded-md bg-red-600 p-1.5 text-white hover:bg-red-700"
            onClick={() => onReject(proposal.id)}
            title="Rejeter"
            type="button"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      )}
    </div>
  );
}
