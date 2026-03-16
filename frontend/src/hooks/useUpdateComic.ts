import { useQueryClient } from "@tanstack/react-query";
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
  const qc = useQueryClient();

  return useOfflineMutation<ComicSeries, Partial<ComicSeries> & { id: number } & Record<string, unknown>>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<ComicSeries>(`/comic_series/${id}`, {
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    onSuccess: (data, variables) => {
      // Mettre à jour la collection avec la réponse serveur (pas de refetch)
      qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.map((c) =>
            c.id === variables.id ? { ...data, _syncPending: false } : c,
          ),
        };
      });
    },
    optimisticUpdate: (queryClient, variables) => {
      const safeFields = safeOptimisticFields(variables);
      // Mettre à jour dans la liste
      queryClient.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.map((c) =>
            c.id === variables.id ? { ...c, ...safeFields, _syncPending: true } : c,
          ),
        };
      });
      // Mettre à jour dans le détail
      queryClient.setQueryData<ComicSeries>(["comic", variables.id], (old) => {
        if (!old) return old;
        return { ...old, ...safeFields, _syncPending: true };
      });
    },
    queryKeysToInvalidate: (variables) => [["comic", variables.id]],
  });
}
