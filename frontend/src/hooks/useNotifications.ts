import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { HydraCollection } from "../types/api";
import type { AppNotification } from "../types/notifications";

export function useUnreadCount() {
  return useQuery({
    queryFn: () =>
      apiFetch<{ count: number }>(endpoints.notifications.unreadCount),
    queryKey: queryKeys.notifications.unreadCount,
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
  });
}

export function useNotifications() {
  return useQuery({
    queryFn: async () => {
      const data = await apiFetch<HydraCollection<AppNotification>>(
        endpoints.notifications.collection,
      );
      return data.member;
    },
    queryKey: queryKeys.notifications.all,
  });
}

export function useMarkAsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<AppNotification>(endpoints.notifications.detail(id), {
        body: JSON.stringify({ read: true }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all });
      queryClient.invalidateQueries({
        queryKey: queryKeys.notifications.unreadCount,
      });
    },
  });
}

export function useMarkAllRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () =>
      apiFetch<{ updated: number }>(endpoints.notifications.readAll, {
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all });
      queryClient.invalidateQueries({
        queryKey: queryKeys.notifications.unreadCount,
      });
    },
  });
}

export function useDeleteNotification() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(endpoints.notifications.detail(id), { method: "DELETE" }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all });
      queryClient.invalidateQueries({
        queryKey: queryKeys.notifications.unreadCount,
      });
    },
  });
}
