import { LogOut, Moon, Search, Sun, Wrench, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Link, Outlet, useNavigate } from "react-router-dom";
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
  const navigate = useNavigate();
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchValue, setSearchValue] = useState("");
  const searchInputRef = useRef<HTMLInputElement>(null);
  useServiceWorker();

  // Sync feedback toasts
  const { error, status, syncedCount } = useSyncStatus();
  const prevStatus = useRef(status);

  useEffect(() => {
    if (status === prevStatus.current) return;
    prevStatus.current = status;

    if (status === "success" && syncedCount > 0) {
      toast.success(`${syncedCount} opération${syncedCount > 1 ? "s" : ""} synchronisée${syncedCount > 1 ? "s" : ""}`);
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
        <div className="relative flex items-center justify-between">
          {/* Contenu normal (logo + actions) */}
          <div className={`flex min-w-0 flex-1 items-center justify-between transition-all duration-300 ${searchOpen ? "pointer-events-none -translate-x-4 opacity-0" : "translate-x-0 opacity-100"}`}>
            <Link className="flex items-center gap-2.5" to="/" viewTransition>
              <img alt="" className="h-8 w-8 rounded-lg" src="/app-icon.png" />
              <span className="font-display text-lg font-bold tracking-tight text-text-primary">
                Bibliothèque
              </span>
            </Link>
            <div className="flex items-center gap-0.5">
              <button
                aria-label="Rechercher"
                className="rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
                onClick={() => setSearchOpen(true)}
                title="Rechercher"
                type="button"
              >
                <Search className="h-5 w-5" strokeWidth={1.5} />
              </button>
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
                {isDark ? <Sun className="h-5 w-5" strokeWidth={1.5} /> : <Moon className="h-5 w-5" strokeWidth={1.5} />}
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

          {/* Barre de recherche — pleine largeur, slide depuis la droite */}
          <form
            className={`absolute inset-0 flex items-center gap-2 transition-all duration-300 ${searchOpen ? "translate-x-0 opacity-100" : "pointer-events-none translate-x-8 opacity-0"}`}
            onSubmit={(e) => {
              e.preventDefault();
              const q = searchValue.trim();
              if (q) {
                navigate(`/?search=${encodeURIComponent(q)}`, { viewTransition: true });
              }
              setSearchOpen(false);
              setSearchValue("");
            }}
          >
            <Search className="h-5 w-5 shrink-0 text-text-muted" strokeWidth={1.5} />
            <input
              autoFocus={searchOpen}
              className="min-w-0 flex-1 bg-transparent text-sm text-text-primary placeholder:text-text-muted focus:outline-none"
              onChange={(e) => setSearchValue(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Escape") {
                  setSearchOpen(false);
                  setSearchValue("");
                }
              }}
              placeholder="Rechercher par titre, auteur, éditeur…"
              ref={searchInputRef}
              type="search"
              value={searchValue}
            />
            <button
              aria-label="Fermer la recherche"
              className="shrink-0 rounded-lg p-2 text-text-secondary hover:bg-surface-tertiary"
              onClick={() => { setSearchOpen(false); setSearchValue(""); }}
              type="button"
            >
              <X className="h-5 w-5" strokeWidth={1.5} />
            </button>
          </form>
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
