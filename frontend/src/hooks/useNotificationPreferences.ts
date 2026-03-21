import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { HydraCollection } from "../types/api";
import type { NotificationChannel } from "../types/enums";
import type { NotificationPreference } from "../types/notifications";

export function useNotificationPreferences() {
  return useQuery({
    queryFn: async () => {
      const data = await apiFetch<HydraCollection<NotificationPreference>>(
        endpoints.notificationPreferences.collection,
      );
      return data.member;
    },
    queryKey: queryKeys.notifications.preferences,
  });
}

export function useUpdatePreference() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ channel, id }: { channel: NotificationChannel; id: number }) =>
      apiFetch<NotificationPreference>(endpoints.notificationPreferences.detail(id), {
        body: JSON.stringify({ channel }),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.preferences });
    },
  });
}
