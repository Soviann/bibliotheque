import { Loader2, Search, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import EmptyState from "../components/EmptyState";
import ProposalCard from "../components/ProposalCard";
import SelectListbox from "../components/SelectListbox";
import { useAcceptProposal, useEnrichmentProposals, useRejectProposal } from "../hooks/useEnrichment";
import type { EnrichmentProposal } from "../types/api";
import {
  EnrichableFieldLabel,
  EnrichmentConfidenceLabel,
  ProposalStatus,
  type SelectOption,
} from "../types/enums";

const ACTIONABLE_STATUSES: string[] = [ProposalStatus.PENDING, ProposalStatus.PRE_ACCEPTED];

const confidenceOptions: SelectOption[] = [
  { label: "Toutes", value: "" },
  { label: EnrichmentConfidenceLabel.high, value: "high" },
  { label: EnrichmentConfidenceLabel.medium, value: "medium" },
  { label: EnrichmentConfidenceLabel.low, value: "low" },
];

export default function EnrichmentReview() {
  const { data: allProposals, isLoading } = useEnrichmentProposals();
  const acceptMutation = useAcceptProposal();
  const rejectMutation = useRejectProposal();

  const [search, setSearch] = useState("");
  const [fieldFilter, setFieldFilter] = useState("");
  const [confidenceFilter, setConfidenceFilter] = useState("");
  const [sourceFilter, setSourceFilter] = useState("");

  // Ne garder que les proposals PENDING et PRE_ACCEPTED
  const proposals = useMemo(
    () => allProposals?.filter((p) => ACTIONABLE_STATUSES.includes(p.status)),
    [allProposals],
  );

  const fieldOptions: SelectOption[] = useMemo(() => {
    const fields = new Set(proposals?.map((p) => p.field) ?? []);
    return [
      { label: "Tous les champs", value: "" },
      ...[...fields].sort().map((f) => ({ label: EnrichableFieldLabel[f] ?? f, value: f })),
    ];
  }, [proposals]);

  const sourceOptions: SelectOption[] = useMemo(() => {
    const sources = new Set(proposals?.map((p) => p.source) ?? []);
    return [
      { label: "Toutes les sources", value: "" },
      ...[...sources].sort().map((s) => ({ label: s, value: s })),
    ];
  }, [proposals]);

  const filteredProposals = useMemo(() => {
    if (!proposals) return [];
    const searchLower = search.toLowerCase();
    return proposals.filter((p) => {
      if (searchLower && !p.comicSeries.title?.toLowerCase().includes(searchLower)) return false;
      if (fieldFilter && p.field !== fieldFilter) return false;
      if (confidenceFilter && p.confidence !== confidenceFilter) return false;
      if (sourceFilter && p.source !== sourceFilter) return false;
      return true;
    });
  }, [proposals, search, fieldFilter, confidenceFilter, sourceFilter]);

  const groupedBySeries = useMemo(() => {
    const map = new Map<number, { proposals: EnrichmentProposal[]; title: string }>();
    for (const proposal of filteredProposals) {
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
  }, [filteredProposals]);

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

      {!isLoading && proposals && proposals.length > 0 && (
        <div className="mt-4 space-y-3">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input
              className="w-full rounded-xl border border-surface-border bg-surface-elevated py-2 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher une série…"
              type="text"
              value={search}
            />
          </div>
          <div className="flex flex-wrap gap-3">
            <div className="min-w-[160px]">
              <SelectListbox
                onChange={setFieldFilter}
                options={fieldOptions}
                placeholder="Champ"
                value={fieldFilter}
              />
            </div>
            <div className="min-w-[140px]">
              <SelectListbox
                onChange={setConfidenceFilter}
                options={confidenceOptions}
                placeholder="Confiance"
                value={confidenceFilter}
              />
            </div>
            <div className="min-w-[160px]">
              <SelectListbox
                onChange={setSourceFilter}
                options={sourceOptions}
                placeholder="Source"
                value={sourceFilter}
              />
            </div>
          </div>
          <p className="text-sm text-text-tertiary">
            {filteredProposals.length} proposition{filteredProposals.length !== 1 ? "s" : ""}
          </p>
        </div>
      )}

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
