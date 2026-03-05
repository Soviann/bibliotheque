import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useUpdateComic() {
  return useOfflineMutation<ComicSeries, Partial<ComicSeries> & { id: number } & Record<string, unknown>>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<ComicSeries>(`/comic_series/${id}`, {
        body: JSON.stringify(data),
        method: "PUT",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    optimisticUpdate: (qc, variables) => {
      // Mettre à jour dans la liste
      qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.map((c) =>
            c.id === variables.id ? { ...c, ...variables, _syncPending: true } : c,
          ),
        };
      });
      // Mettre à jour dans le détail
      qc.setQueryData<ComicSeries>(["comic", variables.id], (old) => {
        if (!old) return old;
        return { ...old, ...variables, _syncPending: true };
      });
    },
    queryKeysToInvalidate: [["comics"], ["comic"]],
  });
}
