import { useMutation, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection, Tome } from "../types/api";

/**
 * Marque un tome comme acheté via PATCH et met à jour la liste optimistiquement.
 */
export function useBuyTome() {
  const queryClient = useQueryClient();

  return useMutation<
    Tome,
    Error,
    { seriesId: number; tomeId: number },
    { previousData: HydraCollection<ComicSeries> | undefined }
  >({
    mutationFn: ({ tomeId }) =>
      apiFetch<Tome>(endpoints.tomes.detail(tomeId), {
        body: JSON.stringify({ bought: true }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onError: (_err, _variables, context) => {
      if (context?.previousData) {
        queryClient.setQueryData(queryKeys.comics.all, context.previousData);
      }
    },
    onMutate: (variables) => {
      queryClient.cancelQueries({ queryKey: queryKeys.comics.all });

      const previousData = queryClient.getQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all);

      queryClient.setQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all, (old) => {
        if (!old) return old;
        const idx = old.member.findIndex((s) => s.id === variables.seriesId);
        if (idx === -1) return old;
        const updated = [...old.member];
        updated[idx] = {
          ...updated[idx],
          unboughtTomes: updated[idx].unboughtTomes.filter((t) => t.id !== variables.tomeId),
        };
        return { ...old, member: updated };
      });

      return { previousData };
    },
    onSettled: () => {
      // Marquer comme stale sans refetch immédiat — l'optimistic update suffit.
      // Le refetch se fera au prochain focus ou navigation.
      queryClient.invalidateQueries({ queryKey: queryKeys.comics.all, refetchType: "none" });
      queryClient.invalidateQueries({ queryKey: queryKeys.comics.detailPrefix, refetchType: "none" });
    },
  });
}
