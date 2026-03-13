import { GoogleOAuthProvider } from "@react-oauth/google";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { WifiOff } from "lucide-react";
import { lazy, Suspense, useEffect } from "react";
import type { ComponentType } from "react";
import { ErrorBoundary } from "react-error-boundary";
import { createBrowserRouter, createRoutesFromElements, Outlet, Route, RouterProvider, useLocation } from "react-router-dom";
import { Toaster } from "sonner";
import AuthGuard from "./components/AuthGuard";
import ErrorFallback from "./components/ErrorFallback";
import Layout from "./components/Layout";
import Home from "./pages/Home";
import { persister, queryClient } from "./queryClient";

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
const ImportTool = lazyWithRetry(() => import("./pages/ImportTool"));
const Login = lazyWithRetry(() => import("./pages/Login"));
const LookupTool = lazyWithRetry(() => import("./pages/LookupTool"));
const MergeSeries = lazyWithRetry(() => import("./pages/MergeSeries"));
const NotFound = lazyWithRetry(() => import("./pages/NotFound"));
const ToBuy = lazyWithRetry(() => import("./pages/ToBuy"));
const PurgeTool = lazyWithRetry(() => import("./pages/PurgeTool"));
const Tools = lazyWithRetry(() => import("./pages/Tools"));
const Trash = lazyWithRetry(() => import("./pages/Trash"));

function Loading() {
  return <div className="py-12 text-center text-text-muted">Chargement…</div>;
}

function ScrollToTop() {
  const { pathname } = useLocation();
  useEffect(() => { window.scrollTo(0, 0); }, [pathname]);
  return null;
}

function RootLayout() {
  return (
    <Suspense fallback={<Loading />}>
      <ScrollToTop />
      <Outlet />
    </Suspense>
  );
}

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route element={<RootLayout />}>
      <Route element={<Login />} path="/login" />
      <Route
        element={
          <AuthGuard>
            <Layout />
          </AuthGuard>
        }
      >
        <Route element={<Home />} index />
        <Route element={<ComicForm />} path="comic/new" />
        <Route element={<ComicDetail />} path="comic/:id" />
        <Route element={<ComicForm />} path="comic/:id/edit" />
        <Route element={<Tools />} path="tools" />
        <Route element={<ImportTool />} path="tools/import" />
        <Route element={<LookupTool />} path="tools/lookup" />
        <Route element={<MergeSeries />} path="tools/merge-series" />
        <Route element={<PurgeTool />} path="tools/purge" />
        <Route element={<ToBuy />} path="to-buy" />
        <Route element={<Trash />} path="trash" />
        <Route element={<NotFound />} path="*" />
      </Route>
    </Route>
  ),
);

export default function App() {
  return (
    <ErrorBoundary FallbackComponent={ErrorFallback}>
      <GoogleOAuthProvider clientId={import.meta.env.VITE_GOOGLE_CLIENT_ID}>
        <PersistQueryClientProvider
          client={queryClient}
          persistOptions={{
            dehydrateOptions: {
              shouldDehydrateQuery: (query) => {
                const key = query.queryKey[0];
                return key === "comics" || key === "comic";
              },
            },
            maxAge: 60 * 60 * 1000,
            persister,
          }}
        >
          <RouterProvider router={router} />
          <Toaster position="top-center" richColors />
          <ReactQueryDevtools initialIsOpen={false} />
        </PersistQueryClientProvider>
      </GoogleOAuthProvider>
    </ErrorBoundary>
  );
}
