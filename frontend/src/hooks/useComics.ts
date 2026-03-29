import { useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

/**
 * Seed le cache détail de chaque série sans déclencher N notifications individuelles.
 * Écrit directement dans le QueryCache pour éviter les cascades de persist.
 */
function seedDetailCache(queryClient: ReturnType<typeof useQueryClient>, members: ComicSeries[]): void {
  const cache = queryClient.getQueryCache();
  for (const series of members) {
    const query = cache.build(queryClient, {
      queryKey: queryKeys.comics.detail(series.id),
    });
    query.setData(series);
  }
}

export function useComics() {
  const queryClient = useQueryClient();

  return useQuery({
    queryFn: async () => {
      const data =
        await apiFetch<HydraCollection<ComicSeries>>(endpoints.comicSeries.collection);
      seedDetailCache(queryClient, data.member);
      return data;
    },
    queryKey: queryKeys.comics.all,
    staleTime: 30 * 60 * 1000,
  });
}
