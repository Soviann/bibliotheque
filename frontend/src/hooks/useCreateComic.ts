import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, CreateComicPayload, HydraCollection } from "../types/api";
import { ComicStatus, ComicType } from "../types/enums";
import { useOfflineMutation } from "./useOfflineMutation";

export function useCreateComic() {
  return useOfflineMutation<ComicSeries, CreateComicPayload>({
    generateTempId: true,
    mutationFn: (data) =>
      apiFetch<ComicSeries>(endpoints.comicSeries.collection, {
        body: JSON.stringify(data),
        method: "POST",
      }),
    offlineOperation: "create",
    offlineResourceType: "comic_series",
    optimisticUpdate: (qc, variables, tempId) => {
      qc.setQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all, (old) => {
        if (!old) return old;
        const tempSeries: ComicSeries = {
          "@id": `/api/comic_series/${tempId}`,
          _syncPending: true,
          amazonUrl: variables.amazonUrl ?? null,
          authors: [],
          boughtCount: 0,
          coveredCount: 0,
          coverImage: null,
          coverUrl: variables.coverUrl ?? null,
          createdAt: new Date().toISOString(),
          defaultTomeBought: variables.defaultTomeBought ?? false,
          defaultTomeOnNas: variables.defaultTomeOnNas ?? false,
          defaultTomeRead: variables.defaultTomeRead ?? false,
          description: variables.description ?? null,
          onNasCount: 0,
          id: tempId!,
          isOneShot: variables.isOneShot ?? false,
          latestPublishedIssue: null,
          latestPublishedIssueComplete: false,
          latestPublishedIssueUpdatedAt: null,
          maxTomeNumber: null,
          notInterestedBuy: false,
          notInterestedNas: false,
          publishedDate: null,
          publisher: variables.publisher ?? null,
          readCount: 0,
          status: variables.status ?? ComicStatus.BUYING,
          title: variables.title ?? "",
          tomesCount: 0,
          type: variables.type ?? ComicType.BD,
          unboughtTomes: [],
          updatedAt: new Date().toISOString(),
        };
        return {
          ...old,
          member: [tempSeries, ...old.member],
          totalItems: old.totalItems + 1,
        };
      });
    },
    queryKeysToInvalidate: [queryKeys.comics.all],
  });
}
