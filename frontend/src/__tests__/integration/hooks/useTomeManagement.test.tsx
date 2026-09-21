import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { FormData } from "../../../hooks/useComicForm";
import { useTomeManagement } from "../../../hooks/useTomeManagement";
import { createMockLookupResult } from "../../helpers/factories";
import { server } from "../../helpers/server";

const { toastError, toastSuccess } = vi.hoisted(() => ({
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
}));

vi.mock("sonner", () => ({
  toast: { error: toastError, success: toastSuccess },
}));

const API_BASE = "/api";

type UpdateFn = <K extends keyof FormData>(key: K, value: FormData[K]) => void;

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
    title: "Tintin",
    tomes: [],
    type: "BD",
    ...overrides,
  };
}

describe("useTomeManagement", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    toastSuccess.mockClear();
    toastError.mockClear();
  });

  it("ajoute un tome avec le numéro suivant", () => {
    let currentForm = createForm({
      tomes: [
        {
          bought: false,
          id: 1,
          isbn: "",
          isHorsSerie: false,
          number: 1,
          onNas: false,
          read: false,
          title: "",
          tomeEnd: "",
        },
      ],
    });
    const update: UpdateFn = (key, value) => {
      currentForm = { ...currentForm, [key]: value };
    };

    const { result } = renderHook(() => useTomeManagement(currentForm, update));

    act(() => {
      result.current.addTome();
    });

    expect(currentForm.tomes).toHaveLength(2);
    expect(currentForm.tomes[1].number).toBe(2);
  });

  it("supprime un tome par index", () => {
    let currentForm = createForm({
      tomes: [
        {
          bought: false,
          id: 1,
          isbn: "",
          isHorsSerie: false,
          number: 1,
          onNas: false,
          read: false,
          title: "Tome 1",
          tomeEnd: "",
        },
        {
          bought: false,
          id: 2,
          isbn: "",
          isHorsSerie: false,
          number: 2,
          onNas: false,
          read: false,
          title: "Tome 2",
          tomeEnd: "",
        },
      ],
    });
    const update: UpdateFn = (key, value) => {
      currentForm = { ...currentForm, [key]: value };
    };

    const { result } = renderHook(() => useTomeManagement(currentForm, update));

    act(() => {
      result.current.removeTome(0);
    });

    expect(currentForm.tomes).toHaveLength(1);
    expect(currentForm.tomes[0].number).toBe(2);
  });

  it("ajoute un lot de tomes en respectant batchFrom et batchTo", () => {
    let currentForm = createForm();
    const update: UpdateFn = (key, value) => {
      currentForm = { ...currentForm, [key]: value };
    };

    const { result } = renderHook(() => useTomeManagement(currentForm, update));

    act(() => {
      result.current.setBatchFrom(1);
      result.current.setBatchTo(3);
    });

    act(() => {
      result.current.addBatchTomes();
    });

    expect(currentForm.tomes).toHaveLength(3);
    expect(currentForm.tomes.map((t) => t.number)).toEqual([1, 2, 3]);
  });

  it("lookupTomeIsbn met à jour les informations du tome", async () => {
    server.use(
      http.get(`${API_BASE}/lookup/isbn`, () =>
        HttpResponse.json(
          createMockLookupResult({
            isbn: "9782203001045",
            title: "Les Cigares du Pharaon",
            tomeEnd: 4,
          }),
        ),
      ),
    );

    let currentForm = createForm({
      tomes: [
        {
          bought: false,
          id: 1,
          isbn: "9782203001045",
          isHorsSerie: false,
          number: 4,
          onNas: false,
          read: false,
          title: "",
          tomeEnd: "",
        },
      ],
    });
    const update: UpdateFn = (key, value) => {
      currentForm = { ...currentForm, [key]: value };
    };

    const { result } = renderHook(() => useTomeManagement(currentForm, update));

    act(() => {
      result.current.lookupTomeIsbn(0);
    });

    await waitFor(() => {
      expect(result.current.tomeLookupLoading).toBeNull();
    });

    expect(currentForm.tomes[0].title).toBe("Les Cigares du Pharaon");
    expect(currentForm.tomes[0].tomeEnd).toBe("4");
    expect(toastSuccess).toHaveBeenCalledWith("Tome 4 : informations récupérées");
  });

  describe("lookupTomeTitle", () => {
    it("nominal : recherche avec titre série + titre tome et applique tomeTitle", async () => {
      let requestedTitle = "";
      server.use(
        http.get(`${API_BASE}/lookup/title`, ({ request }) => {
          const url = new URL(request.url);
          requestedTitle = url.searchParams.get("title") ?? "";
          return HttpResponse.json(
            createMockLookupResult({
              isbn: "9782203001045",
              seriesTitle: "Tintin",
              title: "Tintin - Les Cigares du Pharaon",
              tomeEnd: 4,
              tomeTitle: "Les Cigares du Pharaon",
            }),
          );
        }),
      );

      let currentForm = createForm({
        title: "Tintin",
        tomes: [
          {
            bought: false,
            id: 1,
            isbn: "",
            isHorsSerie: false,
            number: 4,
            onNas: false,
            read: false,
            title: "Les Cigares du Pharaon",
            tomeEnd: "",
          },
        ],
      });
      const update: UpdateFn = (key, value) => {
        currentForm = { ...currentForm, [key]: value };
      };

      const { result } = renderHook(() =>
        useTomeManagement(currentForm, update),
      );

      act(() => {
        result.current.lookupTomeTitle(0);
      });

      expect(result.current.tomeTitleLookupLoading).toBe(0);

      await waitFor(() => {
        expect(result.current.tomeTitleLookupLoading).toBeNull();
      });

      expect(requestedTitle).toBe("Tintin Les Cigares du Pharaon");
      expect(currentForm.tomes[0].title).toBe("Les Cigares du Pharaon");
      expect(currentForm.tomes[0].isbn).toBe("9782203001045");
      expect(currentForm.tomes[0].tomeEnd).toBe("4");
      expect(toastSuccess).toHaveBeenCalledWith(
        "Tome 4 : informations récupérées",
      );
    });

    it("nominal fallback : utilise result.title si tomeTitle est absent", async () => {
      server.use(
        http.get(`${API_BASE}/lookup/title`, () =>
          HttpResponse.json(
            createMockLookupResult({
              isbn: "9782203001045",
              title: "Les Cigares du Pharaon",
            }),
          ),
        ),
      );

      let currentForm = createForm({
        title: "Tintin",
        tomes: [
          {
            bought: false,
            id: 1,
            isbn: "",
            isHorsSerie: false,
            number: 4,
            onNas: false,
            read: false,
            title: "Cigares",
            tomeEnd: "",
          },
        ],
      });
      const update: UpdateFn = (key, value) => {
        currentForm = { ...currentForm, [key]: value };
      };

      const { result } = renderHook(() =>
        useTomeManagement(currentForm, update),
      );

      act(() => {
        result.current.lookupTomeTitle(0);
      });

      await waitFor(() => {
        expect(result.current.tomeTitleLookupLoading).toBeNull();
      });

      expect(currentForm.tomes[0].title).toBe("Les Cigares du Pharaon");
    });

    it("boundary : n'exécute pas la recherche si le titre a moins de 2 caractères", async () => {
      const update = vi.fn();
      const form = createForm({
        tomes: [
          {
            bought: false,
            id: 1,
            isbn: "",
            isHorsSerie: false,
            number: 1,
            onNas: false,
            read: false,
            title: "A",
            tomeEnd: "",
          },
        ],
      });

      const { result } = renderHook(() => useTomeManagement(form, update));

      act(() => {
        result.current.lookupTomeTitle(0);
      });

      expect(result.current.tomeTitleLookupLoading).toBeNull();
      expect(update).not.toHaveBeenCalled();
    });

    it("boundary : recherche avec le titre du tome seul si le titre de série est vide", async () => {
      let requestedTitle = "";
      server.use(
        http.get(`${API_BASE}/lookup/title`, ({ request }) => {
          const url = new URL(request.url);
          requestedTitle = url.searchParams.get("title") ?? "";
          return HttpResponse.json(
            createMockLookupResult({
              isbn: "9782203001045",
              tomeTitle: "Les Cigares du Pharaon",
            }),
          );
        }),
      );

      let currentForm = createForm({
        title: "",
        tomes: [
          {
            bought: false,
            id: 1,
            isbn: "",
            isHorsSerie: false,
            number: 1,
            onNas: false,
            read: false,
            title: "Les Cigares du Pharaon",
            tomeEnd: "",
          },
        ],
      });
      const update: UpdateFn = (key, value) => {
        currentForm = { ...currentForm, [key]: value };
      };

      const { result } = renderHook(() =>
        useTomeManagement(currentForm, update),
      );

      act(() => {
        result.current.lookupTomeTitle(0);
      });

      await waitFor(() => {
        expect(result.current.tomeTitleLookupLoading).toBeNull();
      });

      expect(requestedTitle).toBe("Les Cigares du Pharaon");
    });

    it("error : affiche un toast d'erreur et réinitialise le chargement en cas d'échec API", async () => {
      server.use(
        http.get(`${API_BASE}/lookup/title`, () =>
          HttpResponse.json({ error: "Not found" }, { status: 404 }),
        ),
      );

      const update = vi.fn();
      const form = createForm({
        title: "Tintin",
        tomes: [
          {
            bought: false,
            id: 1,
            isbn: "",
            isHorsSerie: false,
            number: 1,
            onNas: false,
            read: false,
            title: "Inconnu",
            tomeEnd: "",
          },
        ],
      });

      const { result } = renderHook(() => useTomeManagement(form, update));

      act(() => {
        result.current.lookupTomeTitle(0);
      });

      await waitFor(() => {
        expect(result.current.tomeTitleLookupLoading).toBeNull();
      });

      expect(update).not.toHaveBeenCalled();
      expect(toastError).toHaveBeenCalledWith(
        "Échec de la recherche par titre",
      );
    });
  });
});
