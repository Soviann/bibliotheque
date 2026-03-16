import { useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useComics() {
  const queryClient = useQueryClient();

  return useQuery({
    queryFn: async () => {
      const data =
        await apiFetch<HydraCollection<ComicSeries>>(endpoints.comicSeries.collection);
      // Seeder le cache individuel pour la navigation hors ligne
      data.member.forEach((series) => {
        queryClient.setQueryData(queryKeys.comics.detail(series.id), series);
      });
      return data;
    },
    queryKey: queryKeys.comics.all,
    staleTime: 30 * 60 * 1000,
  });
}
