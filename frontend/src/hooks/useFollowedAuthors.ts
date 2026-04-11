import { useMutation, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { Author } from "../types/api";

export function useToggleAuthorFollow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ follow, id }: { follow: boolean; id: number }) =>
      apiFetch<Author>(`${endpoints.authors}/${id}`, {
        body: JSON.stringify({ followedForNewSeries: follow }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.comics.detailPrefix,
      });
    },
  });
}
