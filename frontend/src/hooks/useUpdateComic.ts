import { useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection, UpdateComicPayload } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

// Champs sûrs à mettre à jour de façon optimiste (mêmes types dans le payload et dans ComicSeries)
function safeOptimisticFields(variables: UpdateComicPayload): Partial<ComicSeries> {
  const safe: Partial<ComicSeries> = {};
  if (variables.title !== undefined) safe.title = variables.title;
  if (variables.description !== undefined) safe.description = variables.description;
  if (variables.publisher !== undefined) safe.publisher = variables.publisher;
  if (variables.coverUrl !== undefined) safe.coverUrl = variables.coverUrl;
  if (variables.status !== undefined) safe.status = variables.status;
  if (variables.type !== undefined) safe.type = variables.type;
  if (variables.isOneShot !== undefined) safe.isOneShot = variables.isOneShot;
  return safe;
}

export function useUpdateComic() {
  const qc = useQueryClient();

  return useOfflineMutation<ComicSeries, UpdateComicPayload>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<ComicSeries>(endpoints.comicSeries.detail(id), {
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    onSuccess: (data, variables) => {
      // Mettre à jour la collection avec la réponse serveur (pas de refetch)
      qc.setQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all, (old) => {
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
      queryClient.setQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all, (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.map((c) =>
            c.id === variables.id ? { ...c, ...safeFields, _syncPending: true } : c,
          ),
        };
      });
      // Mettre à jour dans le détail
      queryClient.setQueryData<ComicSeries>(queryKeys.comics.detail(variables.id), (old) => {
        if (!old) return old;
        return { ...old, ...safeFields, _syncPending: true };
      });
    },
    queryKeysToInvalidate: (variables) => [queryKeys.comics.detail(variables.id)],
  });
}
