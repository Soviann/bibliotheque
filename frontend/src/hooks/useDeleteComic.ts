import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";

export function useDeleteComic() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(`/comic_series/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["comics"] });
      void queryClient.invalidateQueries({ queryKey: ["trash"] });
    },
  });
}
