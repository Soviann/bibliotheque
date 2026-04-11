import { Bell, CheckCheck, Loader2, Settings, Trash2 } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import EmptyState from "../components/EmptyState";
import {
  useDeleteNotification,
  useMarkAllRead,
  useMarkAsRead,
  useNotifications,
} from "../hooks/useNotifications";
import type { AppNotification } from "../types/notifications";
import {
  NotificationEntityType,
  NotificationTypeLabel,
  type NotificationType,
} from "../types/enums";

function formatRelativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60_000);
  if (minutes < 1) return "À l'instant";
  if (minutes < 60) return `Il y a ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `Il y a ${hours}h`;
  const days = Math.floor(hours / 24);
  return `Il y a ${days}j`;
}

function buildEntityUrl(notification: AppNotification): string | null {
  if (!notification.relatedEntityType || !notification.relatedEntityId)
    return null;
  return notification.relatedEntityType === NotificationEntityType.COMIC_SERIES
    ? `/comic/${notification.relatedEntityId}`
    : notification.relatedEntityType ===
        NotificationEntityType.ENRICHMENT_PROPOSAL
      ? "/tools/enrichment-review"
      : null;
}

export default function Notifications() {
  const { data: notifications, isLoading } = useNotifications();
  const markAsRead = useMarkAsRead();
  const markAllRead = useMarkAllRead();
  const deleteNotification = useDeleteNotification();
  const navigate = useNavigate();

  const handleClick = (notification: AppNotification) => {
    if (!notification.read) {
      markAsRead.mutate(notification.id);
    }
    const url = buildEntityUrl(notification);
    if (url) {
      navigate(url, { viewTransition: true });
    }
  };

  const handleMarkAllRead = () => {
    markAllRead.mutate(undefined, {
      onSuccess: (data) =>
        toast.success(
          `${data.updated} notification(s) marquée(s) comme lue(s)`,
        ),
    });
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-6">
      <Breadcrumb items={[{ label: "Notifications" }]} />
      <div className="flex items-center justify-between">
        <h1 className="font-display text-xl font-bold text-text-primary">
          Notifications
        </h1>
        <div className="flex items-center gap-2">
          <button
            className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-text-secondary hover:bg-surface-tertiary disabled:opacity-50"
            disabled={!notifications || notifications.every((n) => n.read)}
            onClick={handleMarkAllRead}
            type="button"
          >
            <CheckCheck className="h-4 w-4" />
            Tout lire
          </button>
          <Link
            aria-label="Paramètres des notifications"
            className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
            to="/settings/notifications"
            viewTransition
          >
            <Settings className="h-4 w-4" />
          </Link>
        </div>
      </div>

      {isLoading && (
        <div className="mt-8 flex items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      )}

      {!isLoading && (!notifications || notifications.length === 0) && (
        <EmptyState
          description="Vous n'avez aucune notification"
          icon={Bell}
          title="Aucune notification"
        />
      )}

      {!isLoading && notifications && notifications.length > 0 && (
        <div className="mt-4 space-y-2">
          {notifications.map((notification) => (
            <div
              className={`flex items-start gap-3 rounded-lg border p-3 transition ${
                notification.read
                  ? "border-surface-border bg-surface-primary"
                  : "border-primary-200 bg-primary-50 dark:border-primary-800 dark:bg-primary-950/20"
              }`}
              key={notification.id}
            >
              <button
                className="min-w-0 flex-1 cursor-pointer text-left"
                onClick={() => handleClick(notification)}
                type="button"
              >
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-text-primary">
                    {notification.title}
                  </span>
                  {!notification.read && (
                    <span className="h-2 w-2 shrink-0 rounded-full bg-primary-500" />
                  )}
                </div>
                <p className="mt-0.5 text-sm text-text-secondary">
                  {notification.message}
                </p>
                <div className="mt-1 flex items-center gap-2 text-xs text-text-tertiary">
                  <span>
                    {
                      NotificationTypeLabel[
                        notification.type as NotificationType
                      ]
                    }
                  </span>
                  <span>{formatRelativeTime(notification.createdAt)}</span>
                </div>
              </button>
              <button
                aria-label="Supprimer"
                className="shrink-0 rounded-md p-1 text-text-muted hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-950/30"
                onClick={() => deleteNotification.mutate(notification.id)}
                type="button"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
