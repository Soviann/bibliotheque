import { useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useComics() {
  const queryClient = useQueryClient();

  return useQuery({
    queryFn: async () => {
      const data =
        await apiFetch<HydraCollection<ComicSeries>>("/comic_series");
      // Seeder le cache individuel pour la navigation hors ligne
      data.member.forEach((series) => {
        queryClient.setQueryData(["comic", series.id], series);
      });
      return data;
    },
    queryKey: ["comics"],
    staleTime: 30 * 60 * 1000,
  });
}
