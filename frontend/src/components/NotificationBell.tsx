import { Bell } from "lucide-react";
import { Link } from "react-router-dom";
import { useUnreadCount } from "../hooks/useNotifications";

export default function NotificationBell() {
  const { data } = useUnreadCount();
  const count = data?.count ?? 0;

  return (
    <Link
      aria-label={
        count > 0 ? `${count} notifications non lues` : "Notifications"
      }
      className="relative inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-text-muted hover:text-text-secondary"
      to="/notifications"
      viewTransition
    >
      <Bell className="h-5 w-5" />
      {count > 0 && (
        <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </Link>
  );
}
