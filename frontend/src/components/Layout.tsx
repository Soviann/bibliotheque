import {
  BookOpen,
  Heart,
  Home,
  LogOut,
  Plus,
  Search,
  Trash2,
} from "lucide-react";
import { NavLink, Outlet } from "react-router-dom";
import { Toaster } from "sonner";
import { useAuth } from "../hooks/useAuth";

const navItems = [
  { icon: Home, label: "Accueil", to: "/" },
  { icon: Heart, label: "Wishlist", to: "/wishlist" },
  { icon: Plus, label: "Ajouter", to: "/comic/new" },
  { icon: Search, label: "Recherche", to: "/search" },
  { icon: Trash2, label: "Corbeille", to: "/trash" },
];

export default function Layout() {
  const { logout } = useAuth();

  return (
    <div className="flex min-h-screen flex-col">
      {/* Header desktop */}
      <header className="hidden border-b border-slate-200 bg-white px-6 py-3 md:flex md:items-center md:justify-between">
        <NavLink to="/" className="flex items-center gap-2 text-lg font-bold text-primary-700">
          <BookOpen className="h-6 w-6" />
          Bibliothèque
        </NavLink>
        <nav className="flex items-center gap-4">
          {navItems.map(({ icon: Icon, label, to }) => (
            <NavLink
              className={({ isActive }) =>
                `flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                  isActive
                    ? "bg-primary-100 text-primary-700"
                    : "text-slate-600 hover:bg-slate-100"
                }`
              }
              key={to}
              to={to}
            >
              <Icon className="h-4 w-4" />
              {label}
            </NavLink>
          ))}
          <button
            className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
            onClick={logout}
            type="button"
          >
            <LogOut className="h-4 w-4" />
            Déconnexion
          </button>
        </nav>
      </header>

      {/* Main content */}
      <main className="flex-1 px-4 py-4 pb-20 md:px-6 md:pb-4">
        <Outlet />
      </main>

      {/* Bottom nav mobile */}
      <nav className="fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around border-t border-slate-200 bg-white py-2 md:hidden">
        {navItems.map(({ icon: Icon, label, to }) => (
          <NavLink
            className={({ isActive }) =>
              `flex flex-col items-center gap-0.5 px-2 text-xs ${
                isActive ? "text-primary-600" : "text-slate-400"
              }`
            }
            key={to}
            to={to}
          >
            <Icon className="h-5 w-5" />
            {label}
          </NavLink>
        ))}
      </nav>

      <Toaster position="top-center" richColors />
    </div>
  );
}
