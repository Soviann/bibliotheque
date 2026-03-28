import { Home, Plus, ShoppingCart, Trash2 } from "lucide-react";
import type { ComponentType } from "react";
import { Link, useLocation } from "react-router-dom";

interface Tab {
  color: string;
  dotColor: string;
  icon: ComponentType<{ className?: string; strokeWidth?: number }>;
  isActive: (pathname: string) => boolean;
  label: string;
  to: string;
}

const tabs: Tab[] = [
  {
    color: "text-primary-600 dark:text-primary-400",
    dotColor: "bg-primary-500",
    icon: Home,
    isActive: (pathname) => pathname === "/",
    label: "Accueil",
    to: "/",
  },
  {
    color: "text-accent-sage dark:text-accent-sage",
    dotColor: "bg-accent-sage",
    icon: ShoppingCart,
    isActive: (pathname) => pathname === "/to-buy",
    label: "À acheter",
    to: "/to-buy",
  },
  {
    color: "text-primary-500 dark:text-primary-400",
    dotColor: "bg-primary-500",
    icon: Plus,
    isActive: (pathname) => pathname === "/quick-add",
    label: "Ajouter",
    to: "/quick-add",
  },
  {
    color: "text-accent-danger dark:text-accent-danger",
    dotColor: "bg-accent-danger",
    icon: Trash2,
    isActive: (pathname) => pathname === "/trash",
    label: "Corbeille",
    to: "/trash",
  },
];

export default function BottomNav() {
  const { pathname } = useLocation();

  return (
    <nav className="grain fixed bottom-0 left-0 right-0 z-50 mx-auto h-[var(--bottom-nav-h)] border-t border-surface-border bg-surface-primary/95 backdrop-blur-md dark:border-white/10 dark:bg-surface-primary/85 dark:backdrop-blur-xl lg:max-w-4xl lg:left-1/2 lg:-translate-x-1/2 lg:rounded-t-xl lg:border-x">
      <div className="flex h-full items-center justify-around">
        {tabs.map(({ color, dotColor, icon: Icon, isActive, label, to }) => {
          const active = isActive(pathname);
          return (
            <Link
              aria-current={active ? "page" : undefined}
              className={`relative flex flex-col items-center justify-center gap-1 px-3 text-xs font-medium transition-colors ${
                active ? color : "text-text-muted dark:text-text-secondary"
              }`}
              key={label}
              to={to}
              viewTransition
            >
              <Icon className="h-5 w-5" strokeWidth={1.5} />
              <span>
                {label}
              </span>
              {/* Active dot indicator */}
              {active && (
                <span className={`absolute -bottom-1 h-1 w-1 rounded-full ${dotColor} dark:shadow-[0_0_6px_currentColor]`} />
              )}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
