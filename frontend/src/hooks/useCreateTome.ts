import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, Tome } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useCreateTome(seriesId: number) {
  return useOfflineMutation<Tome, Partial<Tome> & Record<string, unknown>>({
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
          bought: (variables.bought as boolean) ?? false,
          createdAt: new Date().toISOString(),
          downloaded: (variables.downloaded as boolean) ?? false,
          id: tempId!,
          isHorsSerie: (variables.isHorsSerie as boolean) ?? false,
          isbn: (variables.isbn as string) ?? null,
          number: (variables.number as number) ?? 0,
          onNas: (variables.onNas as boolean) ?? false,
          read: (variables.read as boolean) ?? false,
          title: (variables.title as string) ?? null,
          tomeEnd: (variables.tomeEnd as number) ?? null,
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
