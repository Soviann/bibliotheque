import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";

// Mock react-router hooks
vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual<typeof import("react-router-dom")>("react-router-dom");
  return {
    ...actual,
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: "1" }),
  };
});

vi.mock("../../hooks/useComic", () => ({
  useComic: () => ({
    data: {
      "@id": "/api/comic_series/1",
      authors: [{ "@id": "/api/authors/1", id: 1, name: "Auteur Test" }],
      coverImage: null,
      coverUrl: null,
      description: "Une description",
      id: 1,
      isOneShot: false,
      publisher: "Editeur",
      status: "buying",
      title: "Série Test",
      tomes: [],
      type: "bd",
    },
    isLoading: false,
  }),
}));

vi.mock("../../hooks/useDeleteComic", () => ({
  useDeleteComic: () => ({ isPending: false, mutate: vi.fn() }),
}));

function renderComicDetail() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <ComicDetail />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

let ComicDetail: React.ComponentType;

describe("ComicDetail", () => {
  it("places destructive button (Supprimer) before primary button (Modifier) in action bar", async () => {
    ({ default: ComicDetail } = await import("../../pages/ComicDetail"));
    renderComicDetail();

    const deleteButton = screen.getByRole("button", { name: /Supprimer/ });
    const editLink = screen.getByRole("link", { name: /Modifier/ });

    // Supprimer should appear before Modifier in DOM order (left position)
    const bar = deleteButton.closest("div")!;
    const children = Array.from(bar.querySelectorAll("button, a"));
    expect(children.indexOf(deleteButton)).toBeLessThan(children.indexOf(editLink));
  });
});
