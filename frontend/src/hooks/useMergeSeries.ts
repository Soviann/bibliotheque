import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { MergeGroup, MergePreview } from "../types/api";

interface DetectParams {
  all?: boolean;
  type?: string;
}

export function useDetectMergeGroups() {
  return useMutation({
    mutationFn: (params: DetectParams) =>
      apiFetch<MergeGroup[]>("/merge-series/detect", {
        body: JSON.stringify(params),
        method: "POST",
      }),
  });
}

export function useMergePreview() {
  return useMutation({
    mutationFn: (seriesIds: number[]) =>
      apiFetch<MergePreview>("/merge-series/preview", {
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
        "/merge-series/execute",
        {
          body: JSON.stringify(preview),
          method: "POST",
        },
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["comics"] });
    },
  });
}
