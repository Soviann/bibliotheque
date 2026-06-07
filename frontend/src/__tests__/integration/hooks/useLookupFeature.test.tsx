import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useLookupFeature } from "../../../hooks/useLookupFeature";
import type { FormData } from "../../../hooks/useComicForm";
import { createMockLookupResult } from "../../helpers/factories";
import { server } from "../../helpers/server";
import { createTestQueryClient } from "../../helpers/test-utils";

const { toastSuccess, toastWarning, toastError } = vi.hoisted(() => ({
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
  toastWarning: vi.fn(),
}));

vi.mock("sonner", () => ({
  toast: { error: toastError, success: toastSuccess, warning: toastWarning },
}));

const API_BASE = "/api";
const ISBN = "9782756006383";

type UpdateFn = <K extends keyof FormData>(key: K, value: FormData[K]) => void;

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

function createForm(overrides: Partial<FormData> = {}): FormData {
  return {
    amazonUrl: "",
    authors: [],
    coverUrl: "",
    defaultTomeBought: false,
    defaultTomeOnNas: false,
    defaultTomeRead: false,
    description: "",
    isOneShot: false,
    latestPublishedIssue: "",
    latestPublishedIssueComplete: false,
    lookupCompletedAt: null,
    publishedDate: "",
    publisher: "",
    status: "buying",
    title: "Akademy",
    tomes: [],
    type: "BD",
    ...overrides,
  };
}

/** Rend le hook en mode ISBN et attend que le résultat ISBN soit chargé. */
async function renderIsbnMode(update: ReturnType<typeof vi.fn>) {
  const form = createForm();
  const view = renderHook(
    () => useLookupFeature(form, update as unknown as UpdateFn),
    { wrapper: createWrapper() },
  );

  act(() => {
    view.result.current.setLookupMode("isbn");
    view.result.current.setLookupIsbn(ISBN);
  });

  await waitFor(() =>
    expect(view.result.current.lookupResult.data).toBeDefined(),
  );

  return view;
}

describe("useLookupFeature.applyLookup (ISBN)", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    toastSuccess.mockClear();
    toastWarning.mockClear();
    toastError.mockClear();
  });

  it("one-shot : applique tous les champs (titre inclus)", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({
            description: "Synopsis du one-shot",
            isOneShot: true,
            title: "Le Photographe",
          }),
        ),
      ),
    );

    const update = vi.fn();
    const view = await renderIsbnMode(update);

    await act(async () => {
      await view.result.current.applyLookup();
    });

    expect(update).toHaveBeenCalledWith("title", "Le Photographe");
    expect(update).toHaveBeenCalledWith("description", "Synopsis du one-shot");
    expect(toastSuccess).toHaveBeenCalled();
  });

  it("multi-tomes (isOneShot=false depuis l'ISBN) : ne touche jamais au titre", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({
            description: "Résumé du tome 1",
            isOneShot: false,
            latestPublishedIssue: 5,
            thumbnail: "https://example.test/tome1.jpg",
            title: "La cour des grands",
          }),
        ),
      ),
    );

    const update = vi.fn();
    const view = await renderIsbnMode(update);

    await act(async () => {
      await view.result.current.applyLookup();
    });

    const updatedKeys = update.mock.calls.map((call) => call[0]);
    expect(updatedKeys).not.toContain("title");
    expect(updatedKeys).not.toContain("description");
    expect(updatedKeys).not.toContain("coverUrl");
    expect(updatedKeys).not.toContain("publishedDate");
    expect(update).toHaveBeenCalledWith("isOneShot", false);
    expect(update).toHaveBeenCalledWith("latestPublishedIssue", "5");
    expect(toastWarning).toHaveBeenCalled();
  });

  it("isOneShot null : lookup titre résout multi-tomes → titre conservé", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isOneShot: null,
            title: "La cour des grands",
          }),
        ),
      ),
      http.get(`${API_BASE}/lookup/title`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isOneShot: false,
            latestPublishedIssue: 5,
            title: "La cour des grands",
          }),
        ),
      ),
    );

    const update = vi.fn();
    const view = await renderIsbnMode(update);

    await act(async () => {
      await view.result.current.applyLookup();
    });

    const updatedKeys = update.mock.calls.map((call) => call[0]);
    expect(updatedKeys).not.toContain("title");
    expect(update).toHaveBeenCalledWith("isOneShot", false);
    expect(update).toHaveBeenCalledWith("latestPublishedIssue", "5");
    expect(toastWarning).toHaveBeenCalled();
  });

  it("isOneShot null : lookup titre résout one-shot → applique le titre", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({ isOneShot: null, title: "Le Photographe" }),
        ),
      ),
      http.get(`${API_BASE}/lookup/title`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isOneShot: true,
            title: "Le Photographe",
          }),
        ),
      ),
    );

    const update = vi.fn();
    const view = await renderIsbnMode(update);

    await act(async () => {
      await view.result.current.applyLookup();
    });

    expect(update).toHaveBeenCalledWith("title", "Le Photographe");
    expect(toastSuccess).toHaveBeenCalled();
  });

  it("isOneShot indéterminé même après lookup titre : n'applique rien", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isOneShot: null,
            title: "La cour des grands",
          }),
        ),
      ),
      http.get(`${API_BASE}/lookup/title`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isOneShot: null,
            title: "La cour des grands",
          }),
        ),
      ),
    );

    const update = vi.fn();
    const view = await renderIsbnMode(update);

    await act(async () => {
      await view.result.current.applyLookup();
    });

    expect(update).not.toHaveBeenCalled();
    expect(toastError).toHaveBeenCalled();
  });
});
