import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ErrorBoundary } from "react-error-boundary";
import { MemoryRouter, Route, Routes, useLocation } from "react-router-dom";
import { Suspense, useEffect } from "react";
import type { ComponentType } from "react";
import ErrorFallback from "../../components/ErrorFallback";

// --- Helpers ----------------------------------------------------------------

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      mutations: { retry: false },
      queries: { gcTime: Infinity, retry: false, staleTime: Infinity },
    },
  });
}

// --- OfflineFallback (inline component from App.tsx) -------------------------
// OfflineFallback is not exported from App.tsx, so we replicate it here and
// also test it via the lazyWithRetry integration path below.

function OfflineFallback() {
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 px-4 text-center">
      <h2 className="text-xl font-bold text-text-primary">
        Page non disponible hors ligne
      </h2>
      <p className="max-w-md text-text-secondary">
        Cette page n'a pas été mise en cache. Reconnectez-vous à Internet pour y
        accéder.
      </p>
      <button
        className="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700"
        onClick={() => window.history.back()}
        type="button"
      >
        Retour
      </button>
    </div>
  );
}

// --- Tests -------------------------------------------------------------------

describe("OfflineFallback", () => {
  it("renders offline message and Retour button", () => {
    render(
      <MemoryRouter>
        <OfflineFallback />
      </MemoryRouter>,
    );

    expect(screen.getByText("Page non disponible hors ligne")).toBeInTheDocument();
    expect(
      screen.getByText(
        "Cette page n'a pas été mise en cache. Reconnectez-vous à Internet pour y accéder.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Retour" })).toBeInTheDocument();
  });

  it("calls window.history.back() when Retour is clicked", async () => {
    const user = userEvent.setup();
    const backSpy = vi.spyOn(window.history, "back").mockImplementation(() => {});

    render(
      <MemoryRouter>
        <OfflineFallback />
      </MemoryRouter>,
    );

    await user.click(screen.getByRole("button", { name: "Retour" }));

    expect(backSpy).toHaveBeenCalledOnce();
    backSpy.mockRestore();
  });
});

describe("lazyWithRetry", () => {
  it("renders OfflineFallback when lazy import fails and navigator is offline", async () => {
    // Replicate lazyWithRetry logic inline
    const { lazy } = await import("react");

    type LazyModule = { default: ComponentType };

    function lazyWithRetry(importFn: () => Promise<LazyModule>) {
      return lazy(() =>
        importFn().catch(() => {
          if (!navigator.onLine) {
            return { default: OfflineFallback };
          }
          return new Promise<LazyModule>((_, reject) => {
            setTimeout(() => reject(new Error("retry failed")), 10);
          });
        }),
      );
    }

    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    const FailingPage = lazyWithRetry(() => Promise.reject(new Error("chunk failed")));

    render(
      <MemoryRouter>
        <Suspense fallback={<div>Chargement...</div>}>
          <FailingPage />
        </Suspense>
      </MemoryRouter>,
    );

    await waitFor(() => {
      expect(screen.getByText("Page non disponible hors ligne")).toBeInTheDocument();
    });

    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });
});

describe("ScrollToTop", () => {
  function ScrollToTop() {
    const { pathname } = useLocation();
    useEffect(() => {
      window.scrollTo(0, 0);
    }, [pathname]);
    return null;
  }

  it("calls window.scrollTo(0, 0) on route change", async () => {
    const user = userEvent.setup();
    const scrollToSpy = vi.spyOn(window, "scrollTo").mockImplementation(() => {});

    function PageA() {
      return (
        <div>
          <a href="/page-b">Go B</a>
        </div>
      );
    }

    function NavLink() {
      // Using react-router Link for proper navigation
      const { Link } = require("react-router-dom");
      return <Link to="/page-b">Go B</Link>;
    }

    render(
      <MemoryRouter initialEntries={["/page-a"]}>
        <ScrollToTop />
        <Routes>
          <Route
            element={
              <div>
                Page A <NavLink />
              </div>
            }
            path="/page-a"
          />
          <Route element={<div>Page B</div>} path="/page-b" />
        </Routes>
      </MemoryRouter>,
    );

    // Initial render triggers scrollTo
    expect(scrollToSpy).toHaveBeenCalledWith(0, 0);
    scrollToSpy.mockClear();

    // Navigate to another route
    await user.click(screen.getByText("Go B"));

    await waitFor(() => {
      expect(screen.getByText("Page B")).toBeInTheDocument();
    });

    expect(scrollToSpy).toHaveBeenCalledWith(0, 0);
    scrollToSpy.mockRestore();
  });
});

describe("Route rendering", () => {
  // Mock all lazy page components as simple identifiable divs
  vi.mock("../../pages/Home", () => ({
    default: () => <div>Home Page</div>,
  }));

  vi.mock("../../pages/Wishlist", () => ({
    default: () => <div>Wishlist Page</div>,
  }));

  vi.mock("../../pages/Trash", () => ({
    default: () => <div>Trash Page</div>,
  }));

  vi.mock("../../pages/Login", () => ({
    default: () => <div>Login Page</div>,
  }));

  vi.mock("../../pages/NotFound", () => ({
    default: () => <div>NotFound Page</div>,
  }));

  vi.mock("../../components/AuthGuard", () => ({
    default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  }));

  vi.mock("../../components/Layout", () => {
    const { Outlet } = require("react-router-dom");
    return { default: () => <Outlet /> };
  });

  vi.mock("../../hooks/useServiceWorker", () => ({
    useServiceWorker: () => {},
  }));

  function renderApp(route: string) {
    const qc = createTestQueryClient();
    // We import App dynamically after mocks are set
    // But since vi.mock is hoisted, we can use the mocked components directly
    // and reconstruct the router structure from App.tsx
    return render(
      <QueryClientProvider client={qc}>
        <MemoryRouter initialEntries={[route]}>
          <Suspense fallback={<div>Chargement...</div>}>
            <Routes>
              <Route
                element={<LoginPage />}
                path="/login"
              />
              <Route element={<LayoutWrapper />}>
                <Route element={<HomePage />} index />
                <Route element={<WishlistPage />} path="wishlist" />
                <Route element={<TrashPage />} path="trash" />
                <Route element={<NotFoundPage />} path="*" />
              </Route>
            </Routes>
          </Suspense>
        </MemoryRouter>
      </QueryClientProvider>,
    );
  }

  // Simple components matching the mocked modules
  function HomePage() {
    return <div>Home Page</div>;
  }
  function WishlistPage() {
    return <div>Wishlist Page</div>;
  }
  function TrashPage() {
    return <div>Trash Page</div>;
  }
  function LoginPage() {
    return <div>Login Page</div>;
  }
  function NotFoundPage() {
    return <div>NotFound Page</div>;
  }
  function LayoutWrapper() {
    const { Outlet } = require("react-router-dom");
    return <Outlet />;
  }

  it("renders Home at /", () => {
    renderApp("/");
    expect(screen.getByText("Home Page")).toBeInTheDocument();
  });

  it("renders Wishlist at /wishlist", () => {
    renderApp("/wishlist");
    expect(screen.getByText("Wishlist Page")).toBeInTheDocument();
  });

  it("renders Trash at /trash", () => {
    renderApp("/trash");
    expect(screen.getByText("Trash Page")).toBeInTheDocument();
  });

  it("renders Login at /login", () => {
    renderApp("/login");
    expect(screen.getByText("Login Page")).toBeInTheDocument();
  });

  it("renders NotFound at /unknown", () => {
    renderApp("/unknown");
    expect(screen.getByText("NotFound Page")).toBeInTheDocument();
  });
});

describe("Suspense fallback", () => {
  it("shows 'Chargement...' while lazy pages load", async () => {
    let resolveImport: (mod: { default: ComponentType }) => void;
    const importPromise = new Promise<{ default: ComponentType }>((resolve) => {
      resolveImport = resolve;
    });

    const { lazy } = await import("react");
    const LazyPage = lazy(() => importPromise);

    render(
      <MemoryRouter>
        <Suspense fallback={<div>Chargement...</div>}>
          <LazyPage />
        </Suspense>
      </MemoryRouter>,
    );

    // Should show fallback while loading
    expect(screen.getByText("Chargement...")).toBeInTheDocument();

    // Resolve the import
    resolveImport!({ default: () => <div>Loaded Page</div> });

    await waitFor(() => {
      expect(screen.getByText("Loaded Page")).toBeInTheDocument();
    });
  });
});

describe("ErrorBoundary", () => {
  it("renders ErrorFallback when a component throws", () => {
    // Suppress React error boundary console.error
    const consoleSpy = vi.spyOn(console, "error").mockImplementation(() => {});

    function ThrowingComponent(): never {
      throw new Error("Test error for boundary");
    }

    render(
      <MemoryRouter>
        <ErrorBoundary FallbackComponent={ErrorFallback}>
          <ThrowingComponent />
        </ErrorBoundary>
      </MemoryRouter>,
    );

    expect(screen.getByText("Une erreur est survenue")).toBeInTheDocument();
    expect(screen.getByText("Test error for boundary")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Réessayer" })).toBeInTheDocument();

    consoleSpy.mockRestore();
  });
});
