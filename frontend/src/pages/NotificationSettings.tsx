import { Loader2 } from "lucide-react";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import {
  useNotificationPreferences,
  useUpdatePreference,
} from "../hooks/useNotificationPreferences";
import {
  NotificationChannel,
  NotificationChannelLabel,
  NotificationTypeLabel,
  type NotificationType,
} from "../types/enums";

const channelOptions = Object.values(NotificationChannel);

export default function NotificationSettings() {
  const { data: preferences, isLoading } = useNotificationPreferences();
  const updatePreference = useUpdatePreference();

  const handleChange = (id: number, channel: string) => {
    updatePreference.mutate(
      { channel: channel as NotificationChannel, id },
      {
        onError: () => toast.error("Erreur lors de la mise à jour"),
        onSuccess: () => toast.success("Préférence mise à jour"),
      },
    );
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-6">
      <Breadcrumb
        items={[
          { href: "/notifications", label: "Notifications" },
          { label: "Paramètres" },
        ]}
      />
      <h1 className="font-display text-xl font-bold text-text-primary">
        Paramètres des notifications
      </h1>
      <p className="mt-1 text-sm text-text-secondary">
        Choisissez comment recevoir chaque type de notification.
      </p>

      {isLoading && (
        <div className="mt-8 flex items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      )}

      {!isLoading && preferences && (
        <div className="mt-6 space-y-3">
          {preferences.map((pref) => (
            <div
              className="flex items-center justify-between rounded-lg border border-surface-border bg-surface-primary p-4"
              key={pref.id}
            >
              <span className="text-sm font-medium text-text-primary">
                {NotificationTypeLabel[pref.type as NotificationType]}
              </span>
              <select
                className="rounded-lg border border-surface-border bg-surface-secondary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                onChange={(e) => handleChange(pref.id, e.target.value)}
                value={pref.channel}
              >
                {channelOptions.map((ch) => (
                  <option key={ch} value={ch}>
                    {NotificationChannelLabel[ch]}
                  </option>
                ))}
              </select>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
