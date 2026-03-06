import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { ComicStatus, ComicType } from "../types/enums";
import { useOfflineMutation } from "./useOfflineMutation";

export function useCreateComic() {
  return useOfflineMutation<ComicSeries, Partial<ComicSeries> & Record<string, unknown>>({
    generateTempId: true,
    mutationFn: (data) =>
      apiFetch<ComicSeries>("/comic_series", {
        body: JSON.stringify(data),
        method: "POST",
      }),
    offlineOperation: "create",
    offlineResourceType: "comic_series",
    optimisticUpdate: (qc, variables, tempId) => {
      qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        const tempSeries: ComicSeries = {
          "@id": `/api/comic_series/${tempId}`,
          _syncPending: true,
          authors: [],
          coverImage: null,
          coverUrl: (variables.coverUrl as string) ?? null,
          createdAt: new Date().toISOString(),
          defaultTomeBought: false,
          defaultTomeDownloaded: false,
          defaultTomeRead: false,
          description: (variables.description as string) ?? null,
          id: tempId!,
          isOneShot: (variables.isOneShot as boolean) ?? false,
          latestPublishedIssue: null,
          latestPublishedIssueComplete: false,
          latestPublishedIssueUpdatedAt: null,
          publishedDate: null,
          publisher: (variables.publisher as string) ?? null,
          status: (variables.status as ComicStatus) ?? ComicStatus.BUYING,
          title: (variables.title as string) ?? "",
          tomes: [],
          type: (variables.type as ComicType) ?? ComicType.BD,
          updatedAt: new Date().toISOString(),
        };
        return {
          ...old,
          member: [tempSeries, ...old.member],
          totalItems: old.totalItems + 1,
        };
      });
    },
    queryKeysToInvalidate: [["comics"]],
  });
}
