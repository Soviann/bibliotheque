import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteComic() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(`/comic_series/${id}`, { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    optimisticRollback: (qc, variables) => {
      // Restaurer depuis le snapshot stocké lors de l'optimistic update
      const snapshot = deletedSnapshots.get(variables.id);
      if (snapshot) {
        qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], snapshot);
        deletedSnapshots.delete(variables.id);
      }
    },
    optimisticUpdate: (qc, variables) => {
      const previous = qc.getQueryData<HydraCollection<ComicSeries>>(["comics"]);
      if (previous) {
        deletedSnapshots.set(variables.id, previous);
        qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], {
          ...previous,
          member: previous.member.filter((c) => c.id !== variables.id),
          totalItems: previous.totalItems - 1,
        });
      }
    },
    queryKeysToInvalidate: [["comics"], ["trash"]],
  });
}

// Snapshots pour le rollback optimiste
const deletedSnapshots = new Map<number, HydraCollection<ComicSeries>>();
