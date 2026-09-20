import { useMutation, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, CreateTomePayload, Tome } from "../types/api";

export interface BatchTomesPayload {
  tomes: CreateTomePayload[];
}

export function useCreateTomesBatch(seriesId: number) {
  const queryClient = useQueryClient();

  return useMutation<Tome[], Error, BatchTomesPayload, { previousComic?: ComicSeries }>({
    mutationFn: (data) =>
      apiFetch<Tome[]>(endpoints.comicSeries.batchTomes(seriesId), {
        body: JSON.stringify(data),
        method: "POST",
      }),
    onError: (_err, _variables, context) => {
      if (context?.previousComic) {
        queryClient.setQueryData(
          queryKeys.comics.detail(seriesId),
          context.previousComic,
        );
      }
    },
    onMutate: async (variables) => {
      await queryClient.cancelQueries({
        queryKey: queryKeys.comics.detail(seriesId),
      });

      const previousComic = queryClient.getQueryData<ComicSeries>(
        queryKeys.comics.detail(seriesId),
      );

      if (previousComic) {
        const now = new Date().toISOString();
        const tempTomes: Tome[] = variables.tomes.map((t, index) => ({
          "@id": `/api/tomes/temp-${Date.now()}-${index}`,
          _syncPending: true,
          bought: t.bought ?? false,
          createdAt: now,
          id: -(Date.now() + index),
          isHorsSerie: t.isHorsSerie ?? false,
          isbn: t.isbn ?? null,
          number: t.number ?? 0,
          onNas: t.onNas ?? false,
          read: t.read ?? false,
          title: t.title ?? null,
          tomeEnd: t.tomeEnd ?? null,
          updatedAt: now,
        }));

        queryClient.setQueryData<ComicSeries>(
          queryKeys.comics.detail(seriesId),
          {
            ...previousComic,
            tomes: [...(previousComic.tomes ?? []), ...tempTomes].sort((a, b) => {
              if (a.isHorsSerie !== b.isHorsSerie) return a.isHorsSerie ? 1 : -1;
              return a.number - b.number;
            }),
          },
        );
      }

      return { previousComic };
    },
    onSettled: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.comics.detail(seriesId),
      });
      queryClient.invalidateQueries({
        queryKey: queryKeys.comics.all,
      });
    },
  });
}
