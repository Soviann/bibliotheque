import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, CreateTomePayload, Tome } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useCreateTome(seriesId: number) {
  return useOfflineMutation<Tome, CreateTomePayload>({
    generateTempId: true,
    mutationFn: (data) =>
      apiFetch<Tome>(endpoints.comicSeries.tomes(seriesId), {
        body: JSON.stringify(data),
        method: "POST",
      }),
    offlineOperation: "create",
    offlineParentResourceId: String(seriesId),
    offlineParentResourceType: "comic_series",
    offlineResourceType: "tome",
    optimisticUpdate: (qc, variables, tempId) => {
      qc.setQueryData<ComicSeries>(queryKeys.comics.detail(seriesId), (old) => {
        if (!old) return old;
        const tempTome: Tome = {
          "@id": `/api/tomes/${tempId}`,
          _syncPending: true,
          bought: variables.bought ?? false,
          createdAt: new Date().toISOString(),
          downloaded: variables.downloaded ?? false,
          id: tempId!,
          isHorsSerie: variables.isHorsSerie ?? false,
          isbn: variables.isbn ?? null,
          number: variables.number ?? 0,
          onNas: variables.onNas ?? false,
          read: variables.read ?? false,
          title: variables.title ?? null,
          tomeEnd: variables.tomeEnd ?? null,
          updatedAt: new Date().toISOString(),
        };
        return {
          ...old,
          tomes: [...old.tomes, tempTome].sort((a, b) => {
            if (a.isHorsSerie !== b.isHorsSerie) return a.isHorsSerie ? 1 : -1;
            return a.number - b.number;
          }),
        };
      });
    },
    queryKeysToInvalidate: [queryKeys.comics.detail(seriesId)],
  });
}
