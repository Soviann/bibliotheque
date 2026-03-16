import { useMutation, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type {
  MergeGroup,
  MergePreview,
  MergeSuggestion,
} from "../types/api";

interface DetectParams {
  all?: boolean;
  startsWith?: string;
  type?: string;
}

export function useDetectMergeGroups() {
  return useMutation({
    mutationFn: (params: DetectParams) =>
      apiFetch<MergeGroup[]>(endpoints.mergeSeries.detect, {
        body: JSON.stringify(params),
        method: "POST",
      }),
  });
}

export function useMergePreview() {
  return useMutation({
    mutationFn: (seriesIds: number[]) =>
      apiFetch<MergePreview>(endpoints.mergeSeries.preview, {
        body: JSON.stringify({ seriesIds }),
        method: "POST",
      }),
  });
}

export function useMergeSuggest() {
  return useMutation({
    mutationFn: (seriesIds: number[]) =>
      apiFetch<MergeSuggestion>(endpoints.mergeSeries.suggest, {
        body: JSON.stringify({ seriesIds }),
        method: "POST",
      }),
  });
}

export function useExecuteMerge() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (preview: MergePreview) =>
      apiFetch<{ id: number; title: string; type: string }>(
        endpoints.mergeSeries.execute,
        {
          body: JSON.stringify(preview),
          method: "POST",
        },
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.comics.all });
    },
  });
}
