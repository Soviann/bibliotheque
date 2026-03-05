import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

// Champs sûrs à mettre à jour de façon optimiste (mêmes types dans le payload et dans ComicSeries)
function safeOptimisticFields(variables: Record<string, unknown>): Partial<ComicSeries> {
  const safe: Partial<ComicSeries> = {};
  if (typeof variables.title === "string") safe.title = variables.title;
  if (typeof variables.description === "string" || variables.description === null) safe.description = variables.description as string | null;
  if (typeof variables.publisher === "string" || variables.publisher === null) safe.publisher = variables.publisher as string | null;
  if (typeof variables.coverUrl === "string" || variables.coverUrl === null) safe.coverUrl = variables.coverUrl as string | null;
  if (typeof variables.status === "string") safe.status = variables.status as ComicSeries["status"];
  if (typeof variables.type === "string") safe.type = variables.type as ComicSeries["type"];
  if (typeof variables.isOneShot === "boolean") safe.isOneShot = variables.isOneShot;
  return safe;
}

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
      const safeFields = safeOptimisticFields(variables);
      // Mettre à jour dans la liste
      qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.map((c) =>
            c.id === variables.id ? { ...c, ...safeFields, _syncPending: true } : c,
          ),
        };
      });
      // Mettre à jour dans le détail
      qc.setQueryData<ComicSeries>(["comic", variables.id], (old) => {
        if (!old) return old;
        return { ...old, ...safeFields, _syncPending: true };
      });
    },
    queryKeysToInvalidate: [["comics"], ["comic"]],
  });
}
