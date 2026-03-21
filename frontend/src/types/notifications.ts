import type {
  NotificationChannel,
  NotificationEntityType,
  NotificationType,
} from "./enums";

export interface AppNotification {
  "@id": string;
  createdAt: string;
  id: number;
  message: string;
  metadata: Record<string, unknown> | null;
  read: boolean;
  relatedEntityId: number | null;
  relatedEntityType: NotificationEntityType | null;
  title: string;
  type: NotificationType;
}

export interface NotificationPreference {
  "@id": string;
  channel: NotificationChannel;
  id: number;
  type: NotificationType;
}
