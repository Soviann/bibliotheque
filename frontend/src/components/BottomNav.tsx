import { BookMarked, ScanBarcode, Search, Wrench } from "lucide-react";
import type { ComponentType } from "react";
import { Link, useLocation } from "react-router-dom";

interface Tab {
  icon: ComponentType<{ className?: string; strokeWidth?: number }>;
  isActive: (pathname: string) => boolean;
  label: string;
  to: string;
}

const tabs: Tab[] = [
  {
    icon: BookMarked,
    isActive: (pathname) => pathname === "/",
    label: "Collection",
    to: "/",
  },
  {
    icon: Search,
    isActive: (pathname) => pathname === "/search",
    label: "Recherche",
    to: "/search",
  },
  {
    icon: ScanBarcode,
    isActive: (pathname) => pathname === "/quick-add",
    label: "Scanner",
    to: "/quick-add",
  },
  {
    icon: Wrench,
    isActive: (pathname) => pathname.startsWith("/tools"),
    label: "Outils",
    to: "/tools",
  },
];

export default function BottomNav() {
  const { pathname } = useLocation();

  return (
    <nav className="grain fixed bottom-0 left-0 right-0 z-50 mx-auto h-[var(--bottom-nav-h)] border-t border-surface-border bg-surface-primary/95 backdrop-blur-md dark:border-white/10 dark:bg-surface-primary/85 dark:backdrop-blur-xl lg:left-1/2 lg:max-w-4xl lg:-translate-x-1/2 lg:rounded-t-xl lg:border-x">
      <div className="flex h-full items-center justify-around px-2">
        {tabs.map(({ icon: Icon, isActive, label, to }) => {
          const active = isActive(pathname);

          return (
            <Link
              aria-current={active ? "page" : undefined}
              className={`relative flex flex-1 flex-col items-center justify-center gap-1 py-1 text-center transition-colors ${
                active
                  ? "font-semibold text-amber-600 dark:text-amber-500"
                  : "text-text-muted hover:text-text-primary dark:text-text-secondary dark:hover:text-neutral-200"
              }`}
              key={label}
              to={to}
              viewTransition
            >
              <Icon className="h-5 w-5" strokeWidth={active ? 2.2 : 1.5} />
              <span className="text-[10px]">{label}</span>
              {/* Active dot indicator */}
              {active && (
                <span className="w-1 h-1 rounded-full bg-amber-500 -mt-0.5" />
              )}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
