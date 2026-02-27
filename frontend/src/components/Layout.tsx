import { LogOut, Moon, Sun } from "lucide-react";
import { Outlet } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { useDarkMode } from "../hooks/useDarkMode";
import { useServiceWorker } from "../hooks/useServiceWorker";
import BottomNav from "./BottomNav";
import OfflineBanner from "./OfflineBanner";

export default function Layout() {
  const { logout } = useAuth();
  const { isDark, toggle } = useDarkMode();
  useServiceWorker();

  return (
    <div className="flex min-h-screen flex-col bg-surface-secondary">
      <OfflineBanner />

      {/* Header */}
      <header className="flex items-center justify-between border-b border-surface-border bg-surface-primary px-4 py-2.5">
        <div className="flex items-center gap-2">
          <img alt="" className="h-8 w-8 rounded-lg" src="/app-icon.png" />
          <span className="text-lg font-bold text-text-primary">Bibliothèque</span>
        </div>
        <div className="flex items-center gap-1">
          <button
            className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
            onClick={toggle}
            title={isDark ? "Mode clair" : "Mode sombre"}
            type="button"
          >
            {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
          </button>
          <button
            className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
            onClick={logout}
            title="Déconnexion"
            type="button"
          >
            <LogOut className="h-5 w-5" />
          </button>
        </div>
      </header>

      {/* Main content */}
      <main className="flex-1 px-4 py-4 pb-16 lg:pb-20">
        <Outlet />
      </main>

      <BottomNav />
    </div>
  );
}
