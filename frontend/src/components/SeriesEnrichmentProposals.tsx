import { ChevronDown, ChevronRight, History, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import {
  useAcceptProposal,
  useEnrichmentProposalsBySeries,
  useRejectProposal,
} from "../hooks/useEnrichment";
import { ProposalStatus } from "../types/enums";
import ProposalCard from "./ProposalCard";

const ACTIONABLE_STATUSES: string[] = [
  ProposalStatus.PENDING,
  ProposalStatus.PRE_ACCEPTED,
];

export default function SeriesEnrichmentProposals({
  seriesId,
}: {
  seriesId: number;
}) {
  const { data: proposals } = useEnrichmentProposalsBySeries(seriesId);
  const acceptMutation = useAcceptProposal();
  const rejectMutation = useRejectProposal();
  const [historyOpen, setHistoryOpen] = useState(false);

  const { actionable, resolved } = useMemo(() => {
    if (!proposals) return { actionable: [], resolved: [] };
    return {
      actionable: proposals.filter((p) =>
        ACTIONABLE_STATUSES.includes(p.status),
      ),
      resolved: proposals.filter(
        (p) => !ACTIONABLE_STATUSES.includes(p.status),
      ),
    };
  }, [proposals]);

  if (!proposals || proposals.length === 0) return null;

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
    <div className="mt-6">
      {actionable.length > 0 && (
        <div className="mb-4">
          <div className="flex items-center gap-2 text-sm font-medium text-text-secondary">
            <Sparkles className="h-4 w-4" />
            Propositions à traiter ({actionable.length})
          </div>
          <div className="mt-2 space-y-2">
            {actionable.map((proposal) => (
              <ProposalCard
                key={proposal.id}
                onAccept={handleAccept}
                onReject={handleReject}
                proposal={proposal}
              />
            ))}
          </div>
        </div>
      )}

      {resolved.length > 0 && (
        <div>
          <button
            className="flex items-center gap-2 text-sm font-medium text-text-secondary hover:text-text-primary"
            onClick={() => setHistoryOpen((prev) => !prev)}
            type="button"
          >
            {historyOpen ? (
              <ChevronDown className="h-4 w-4" />
            ) : (
              <ChevronRight className="h-4 w-4" />
            )}
            <History className="h-4 w-4" />
            Historique ({resolved.length})
          </button>

          {historyOpen && (
            <div className="mt-2 space-y-2">
              {resolved.map((proposal) => (
                <ProposalCard key={proposal.id} proposal={proposal} readonly />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
