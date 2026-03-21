import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { HydraCollection, SeriesSuggestion } from "../types/api";
import { SuggestionStatus } from "../types/enums";

export function useSuggestions() {
  return useQuery({
    queryFn: async () => {
      const data = await apiFetch<HydraCollection<SeriesSuggestion>>(
        `${endpoints.suggestions.collection}?status=${SuggestionStatus.PENDING}`,
      );
      return data.member;
    },
    queryKey: queryKeys.suggestions.all,
  });
}

export function useDismissSuggestion() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<SeriesSuggestion>(endpoints.suggestions.detail(id), {
        body: JSON.stringify({ status: SuggestionStatus.DISMISSED }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.suggestions.all });
    },
  });
}

export function useAddSuggestion() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<SeriesSuggestion>(endpoints.suggestions.detail(id), {
        body: JSON.stringify({ status: SuggestionStatus.ADDED }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.suggestions.all });
      const params = new URLSearchParams({
        authors: data.authors.join(","),
        title: data.title,
        type: data.type,
      });
      navigate(`/comic/new?${params.toString()}`, { viewTransition: true });
    },
  });
}
