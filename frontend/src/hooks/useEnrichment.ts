import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { EnrichmentLog, EnrichmentProposal, HydraCollection } from "../types/api";

export function useEnrichmentProposals(status?: string) {
  return useQuery({
    queryFn: async () => {
      const params = status ? `?status=${status}` : "";
      const data = await apiFetch<HydraCollection<EnrichmentProposal>>(
        `${endpoints.enrichment.proposals}${params}`,
      );
      return data.member;
    },
    queryKey: queryKeys.enrichment.proposals(status),
  });
}

export function useEnrichmentLogs(seriesId: number) {
  return useQuery({
    enabled: seriesId > 0,
    queryFn: async () => {
      const data = await apiFetch<HydraCollection<EnrichmentLog>>(
        `${endpoints.enrichment.logs}?comicSeries=${seriesId}`,
      );
      return data.member;
    },
    queryKey: queryKeys.enrichment.logs(seriesId),
  });
}

export function useAcceptProposal() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<EnrichmentProposal>(endpoints.enrichment.accept(id), {
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.enrichment.proposalsPrefix,
      });
      queryClient.invalidateQueries({ queryKey: queryKeys.comics.all });
    },
  });
}

export function useRejectProposal() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<EnrichmentProposal>(endpoints.enrichment.reject(id), {
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.enrichment.proposalsPrefix,
      });
    },
  });
}
