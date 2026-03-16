import { useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useComic(id: number | undefined) {
  const queryClient = useQueryClient();

  return useQuery({
    enabled: id !== undefined,
    initialData: () => {
      // Extraire depuis la collection déjà chargée (navigation hors ligne)
      const collection = queryClient.getQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all);
      return collection?.member.find((s) => s.id === id);
    },
    initialDataUpdatedAt: () =>
      queryClient.getQueryState(queryKeys.comics.all)?.dataUpdatedAt,
    queryFn: () => apiFetch<ComicSeries>(endpoints.comicSeries.detail(id!)),
    queryKey: queryKeys.comics.detail(id),
  });
}
