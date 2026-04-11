import { LogOut, Moon, Sun, Wrench } from "lucide-react";
import { useEffect, useRef } from "react";
import { Link, Outlet } from "react-router-dom";
import { toast } from "sonner";
import { useAuth } from "../hooks/useAuth";
import { useDarkMode } from "../hooks/useDarkMode";
import { useServiceWorker } from "../hooks/useServiceWorker";
import { useSyncStatus } from "../hooks/useSyncStatus";
import BottomNav from "./BottomNav";
import NotificationBell from "./NotificationBell";
import OfflineBanner from "./OfflineBanner";
import SyncErrorBanner from "./SyncErrorBanner";

export default function Layout() {
  const { logout } = useAuth();
  const { isDark, toggle } = useDarkMode();
  useServiceWorker();

  // Sync feedback toasts
  const { error, status, syncedCount } = useSyncStatus();
  const prevStatus = useRef(status);

  useEffect(() => {
    if (status === prevStatus.current) return;
    prevStatus.current = status;

    if (status === "success" && syncedCount > 0) {
      toast.success(
        `${syncedCount} opération${syncedCount > 1 ? "s" : ""} synchronisée${syncedCount > 1 ? "s" : ""}`,
      );
    } else if (status === "error" && error) {
      toast.error(`Erreur de synchronisation : ${error}`);
    }
  }, [error, status, syncedCount]);

  return (
    <div className="flex min-h-screen flex-col bg-surface-secondary">
      <OfflineBanner />
      <SyncErrorBanner />

      {/* Header */}
      <header className="grain sticky top-0 z-40 overflow-hidden border-b border-surface-border bg-surface-primary/90 px-4 py-2.5 backdrop-blur-md dark:border-transparent dark:bg-surface-primary/70">
        <div className="flex items-center justify-between">
          <Link className="flex items-center gap-2.5" to="/" viewTransition>
            <img alt="" className="h-8 w-8 rounded-lg" src="/app-icon.png" />
            <span className="font-display text-lg font-bold tracking-tight text-text-primary">
              Bibliothèque
            </span>
          </Link>
          <div className="flex items-center gap-0.5">
            <NotificationBell />
            <Link
              aria-label="Outils"
              className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
              title="Outils"
              to="/tools"
              viewTransition
            >
              <Wrench className="h-5 w-5" strokeWidth={1.5} />
            </Link>
            <button
              aria-label={isDark ? "Mode clair" : "Mode sombre"}
              className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
              onClick={toggle}
              title={isDark ? "Mode clair" : "Mode sombre"}
              type="button"
            >
              {isDark ? (
                <Sun className="h-5 w-5" strokeWidth={1.5} />
              ) : (
                <Moon className="h-5 w-5" strokeWidth={1.5} />
              )}
            </button>
            <div className="mx-1 h-5 w-px bg-surface-border" />
            <button
              aria-label="Déconnexion"
              className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
              onClick={logout}
              title="Déconnexion"
              type="button"
            >
              <LogOut className="h-5 w-5" strokeWidth={1.5} />
            </button>
          </div>
        </div>
      </header>

      {/* Main content */}
      <main className="flex-1 px-4 py-4 pb-[var(--bottom-nav-h)]">
        <Outlet />
      </main>

      <BottomNav />
    </div>
  );
}
