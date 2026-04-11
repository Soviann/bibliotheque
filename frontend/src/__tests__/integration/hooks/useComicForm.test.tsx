import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import type { ShareLookupResult } from "../../../types/api";
import { useComicForm } from "../../../hooks/useComicForm";
import { createTestQueryClient } from "../../helpers/test-utils";

function createWrapper(
  initialEntries: ({ pathname: string; state?: unknown } | string)[],
  path = "/comic/new",
) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={createTestQueryClient()}>
        <MemoryRouter initialEntries={initialEntries}>
          <Routes>
            <Route path={path} element={<>{children}</>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    );
  };
}

const mockLookupResult: ShareLookupResult = {
  amazonUrl: "https://www.amazon.fr/dp/2723492532",
  authors: "Goscinny, Uderzo",
  description: "Les aventures d'Astérix le Gaulois.",
  isbn: "2723492532",
  isOneShot: false,
  latestPublishedIssue: 38,
  publishedDate: "1961",
  publisher: "Dargaud",
  thumbnail: "https://example.com/cover.jpg",
  title: "Astérix",
  tomeEnd: null,
  tomeNumber: null,
};

describe("useComicForm — pré-remplissage depuis location.state.lookupResult", () => {
  it("pré-remplit le formulaire avec les données ShareLookupResult depuis location.state", () => {
    const { result } = renderHook(() => useComicForm(), {
      wrapper: createWrapper([
        {
          pathname: "/comic/new",
          state: { lookupResult: mockLookupResult },
        },
      ]),
    });

    const { form } = result.current;

    expect(form.title).toBe("Astérix");
    expect(form.description).toBe("Les aventures d'Astérix le Gaulois.");
    expect(form.publisher).toBe("Dargaud");
    expect(form.coverUrl).toBe("https://example.com/cover.jpg");
    expect(form.amazonUrl).toBe("https://www.amazon.fr/dp/2723492532");
    expect(form.publishedDate).toBe("1961");
    expect(form.latestPublishedIssue).toBe("38");
    expect(form.isOneShot).toBe(false);
    expect(form.tomes).toHaveLength(1);
    expect(form.tomes[0].isbn).toBe("2723492532");
    expect(form.tomes[0].number).toBe(1);
  });

  it("ne pré-remplit pas le formulaire si location.state est absent", () => {
    const { result } = renderHook(() => useComicForm(), {
      wrapper: createWrapper(["/comic/new"]),
    });

    const { form } = result.current;

    expect(form.title).toBe("");
    expect(form.description).toBe("");
    expect(form.publisher).toBe("");
    expect(form.coverUrl).toBe("");
    expect(form.amazonUrl).toBe("");
    expect(form.tomes[0].isbn).toBe("");
  });

  it("les champs null dans ShareLookupResult ne remplacent pas les valeurs vides initiales", () => {
    const partialLookup: ShareLookupResult = {
      ...mockLookupResult,
      description: null,
      publisher: null,
      thumbnail: null,
      amazonUrl: null,
      isbn: null,
      latestPublishedIssue: null,
      isOneShot: null,
    };

    const { result } = renderHook(() => useComicForm(), {
      wrapper: createWrapper([
        {
          pathname: "/comic/new",
          state: { lookupResult: partialLookup },
        },
      ]),
    });

    const { form } = result.current;

    expect(form.title).toBe("Astérix");
    expect(form.description).toBe("");
    expect(form.publisher).toBe("");
    expect(form.coverUrl).toBe("");
    expect(form.amazonUrl).toBe("");
    // ISBN null → tome par défaut conservé avec isbn vide
    expect(form.tomes[0].isbn).toBe("");
    // isOneShot null → valeur initiale false conservée
    expect(form.isOneShot).toBe(false);
    // latestPublishedIssue null → valeur initiale vide conservée
    expect(form.latestPublishedIssue).toBe("");
  });
});
