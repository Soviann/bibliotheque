import { Check, Loader2, Sparkles, X } from "lucide-react";
import { useMemo } from "react";
import { Link } from "react-router-dom";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import EmptyState from "../components/EmptyState";
import { useAcceptProposal, useEnrichmentProposals, useRejectProposal } from "../hooks/useEnrichment";
import type { EnrichmentProposal } from "../types/api";
import {
  EnrichableFieldLabel,
  EnrichmentConfidenceColor,
  EnrichmentConfidenceLabel,
  type EnrichmentConfidence,
  ProposalStatus,
} from "../types/enums";

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "boolean") return value ? "Oui" : "Non";
  if (typeof value === "string" && value.length > 100) return value.slice(0, 100) + "…";
  return String(value);
}

function ProposalCard({
  onAccept,
  onReject,
  proposal,
}: {
  onAccept: (id: number) => void;
  onReject: (id: number) => void;
  proposal: EnrichmentProposal;
}) {
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
            {EnrichmentConfidenceLabel[proposal.confidence as EnrichmentConfidence]}
          </span>
          <span className="text-xs text-text-tertiary">{proposal.source}</span>
        </div>
        <div className="mt-1 text-sm text-text-secondary">
          <span className="text-text-tertiary">{formatValue(proposal.currentValue)}</span>
          {" → "}
          <span className="font-medium text-text-primary">{formatValue(proposal.proposedValue)}</span>
        </div>
      </div>
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
    </div>
  );
}

export default function EnrichmentReview() {
  const { data: proposals, isLoading } = useEnrichmentProposals(ProposalStatus.PENDING);
  const acceptMutation = useAcceptProposal();
  const rejectMutation = useRejectProposal();

  const groupedBySeries = useMemo(() => {
    if (!proposals) return new Map<number, { proposals: EnrichmentProposal[]; title: string }>();

    const map = new Map<number, { proposals: EnrichmentProposal[]; title: string }>();
    for (const proposal of proposals) {
      const seriesId = proposal.comicSeries.id;
      const existing = map.get(seriesId);
      if (existing) {
        existing.proposals.push(proposal);
      } else {
        map.set(seriesId, {
          proposals: [proposal],
          title: proposal.comicSeries.title,
        });
      }
    }
    return map;
  }, [proposals]);

  const handleAccept = (id: number) => {
    acceptMutation.mutate(id, {
      onError: () => toast.error("Erreur lors de l'acceptation"),
      onSuccess: () => toast.success("Proposition acceptée"),
    });
  };

  const handleReject = (id: number) => {
    rejectMutation.mutate(id, {
      onError: () => toast.error("Erreur lors du rejet"),
      onSuccess: () => toast.success("Proposition rejetée"),
    });
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Breadcrumb items={[{ href: "/tools", label: "Outils" }, { label: "Revue d'enrichissement" }]} />
      <h1 className="text-xl font-bold text-text-primary">Revue d&apos;enrichissement</h1>

      {isLoading && (
        <div className="mt-8 flex items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      )}

      {!isLoading && groupedBySeries.size === 0 && (
        <EmptyState
          description="Toutes les propositions ont été traitées"
          icon={Sparkles}
          title="Aucune proposition en attente"
        />
      )}

      {!isLoading && groupedBySeries.size > 0 && (
        <div className="mt-4 space-y-6">
          {[...groupedBySeries.entries()].map(([seriesId, { proposals: seriesProposals, title }]) => (
            <div
              className="rounded-xl border border-surface-border bg-surface-primary p-4"
              key={seriesId}
            >
              <Link
                className="text-base font-semibold text-primary-600 hover:underline"
                to={`/comic/${seriesId}`}
              >
                {title}
              </Link>
              <div className="mt-3 space-y-2">
                {seriesProposals.map((proposal) => (
                  <ProposalCard
                    key={proposal.id}
                    onAccept={handleAccept}
                    onReject={handleReject}
                    proposal={proposal}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
