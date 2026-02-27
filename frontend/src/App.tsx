import { QueryClientProvider } from "@tanstack/react-query";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { lazy, Suspense } from "react";
import { ErrorBoundary } from "react-error-boundary";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import { Toaster } from "sonner";
import AuthGuard from "./components/AuthGuard";
import ErrorFallback from "./components/ErrorFallback";
import Layout from "./components/Layout";
import Home from "./pages/Home";
import { queryClient } from "./queryClient";

const ComicDetail = lazy(() => import("./pages/ComicDetail"));
const ComicForm = lazy(() => import("./pages/ComicForm"));
const Login = lazy(() => import("./pages/Login"));
const NotFound = lazy(() => import("./pages/NotFound"));
const Search = lazy(() => import("./pages/Search"));
const Trash = lazy(() => import("./pages/Trash"));
const Wishlist = lazy(() => import("./pages/Wishlist"));

function Loading() {
  return <div className="py-12 text-center text-slate-400">Chargement…</div>;
}

export default function App() {
  return (
    <ErrorBoundary FallbackComponent={ErrorFallback}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
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
                <Route element={<Search />} path="search" />
                <Route element={<Trash />} path="trash" />
                <Route element={<NotFound />} path="*" />
              </Route>
            </Routes>
          </Suspense>
        </BrowserRouter>
        <Toaster position="top-right" richColors />
        <ReactQueryDevtools initialIsOpen={false} />
      </QueryClientProvider>
    </ErrorBoundary>
  );
}
