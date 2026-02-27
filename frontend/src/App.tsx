import { QueryClientProvider } from "@tanstack/react-query";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { WifiOff } from "lucide-react";
import { lazy, Suspense, useEffect } from "react";
import type { ComponentType } from "react";
import { ErrorBoundary } from "react-error-boundary";
import { BrowserRouter, Route, Routes, useLocation } from "react-router-dom";
import { Toaster } from "sonner";
import AuthGuard from "./components/AuthGuard";
import ErrorFallback from "./components/ErrorFallback";
import Layout from "./components/Layout";
import Home from "./pages/Home";
import { queryClient } from "./queryClient";

function OfflineFallback() {
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 px-4 text-center">
      <WifiOff className="h-12 w-12 text-amber-500" />
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

type LazyModule = { default: ComponentType };

function lazyWithRetry(importFn: () => Promise<LazyModule>) {
  return lazy(() =>
    importFn().catch(() => {
      if (!navigator.onLine) {
        return { default: OfflineFallback };
      }
      // En ligne mais échec (nouveau déploiement ?) — retry une fois
      return new Promise<LazyModule>((resolve, reject) => {
        setTimeout(() => importFn().then(resolve).catch(reject), 1500);
      });
    }),
  );
}

const ComicDetail = lazyWithRetry(() => import("./pages/ComicDetail"));
const ComicForm = lazyWithRetry(() => import("./pages/ComicForm"));
const Login = lazyWithRetry(() => import("./pages/Login"));
const NotFound = lazyWithRetry(() => import("./pages/NotFound"));
const Trash = lazyWithRetry(() => import("./pages/Trash"));
const Wishlist = lazyWithRetry(() => import("./pages/Wishlist"));

function Loading() {
  return <div className="py-12 text-center text-text-muted">Chargement…</div>;
}

function ScrollToTop() {
  const { pathname } = useLocation();
  useEffect(() => { window.scrollTo(0, 0); }, [pathname]);
  return null;
}

export default function App() {
  return (
    <ErrorBoundary FallbackComponent={ErrorFallback}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <ScrollToTop />
          <Suspense fallback={<Loading />}>
            <Routes>
              <Route element={<Login />} path="/login" />
              <Route
                element={
                  <AuthGuard>
                    <Layout />
                  </AuthGuard>
                }
              >
                <Route element={<Home />} index />
                <Route element={<Wishlist />} path="wishlist" />
                <Route element={<ComicForm />} path="comic/new" />
                <Route element={<ComicDetail />} path="comic/:id" />
                <Route element={<ComicForm />} path="comic/:id/edit" />
                <Route element={<Trash />} path="trash" />
                <Route element={<NotFound />} path="*" />
              </Route>
            </Routes>
          </Suspense>
        </BrowserRouter>
        <Toaster position="top-center" richColors />
        <ReactQueryDevtools initialIsOpen={false} />
      </QueryClientProvider>
    </ErrorBoundary>
  );
}
