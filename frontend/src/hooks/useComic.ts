import { useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useComic(id: number | undefined) {
  const queryClient = useQueryClient();

  return useQuery({
    enabled: id !== undefined,
    initialData: () => {
      // Extraire depuis la collection déjà chargée (navigation hors ligne)
      const collection = queryClient.getQueryData<HydraCollection<ComicSeries>>(["comics"]);
      return collection?.member.find((s) => s.id === id);
    },
    initialDataUpdatedAt: () =>
      queryClient.getQueryState(["comics"])?.dataUpdatedAt,
    queryFn: () => apiFetch<ComicSeries>(`/comic_series/${id}`),
    queryKey: ["comic", id],
  });
}
