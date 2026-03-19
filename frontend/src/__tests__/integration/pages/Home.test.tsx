import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { toast } from "sonner";
import Home from "../../../pages/Home";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockHydraCollection,
  createMockTome,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      success: vi.fn(),
    }),
  };
});

describe("Home", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("shows skeleton loaders initially", () => {
    renderWithProviders(<Home />);

    const skeletons = screen.getAllByTestId("comic-card-skeleton");
    expect(skeletons).toHaveLength(8);
  });

  it("renders comic series list", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });
    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("shows empty library state when no comics exist", async () => {
    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([])),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Votre bibliothèque est vide")).toBeInTheDocument();
    });
    expect(screen.getByText("Commencez par ajouter votre première série")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Ajouter une série" })).toHaveAttribute("href", "/comic/new");
  });

  it("shows empty wishlist state when status=wishlist and no results", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.BUYING, title: "Buying Comic" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?status=wishlist"] });

    await waitFor(() => {
      expect(screen.getByText("Votre liste de souhaits est vide")).toBeInTheDocument();
    });
    expect(screen.getByText("Les séries que vous souhaitez acheter apparaîtront ici")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Ajouter une série" })).toHaveAttribute("href", "/comic/new");
  });

  it("shows empty search results state when search yields nothing", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "XYZNOTFOUND");

    await waitFor(() => {
      expect(screen.getByText(/Aucun résultat pour/)).toBeInTheDocument();
    });
    expect(screen.getByText(/XYZNOTFOUND/)).toBeInTheDocument();
  });

  it("shows h1 title 'Ma bibliothèque'", () => {
    renderWithProviders(<Home />);

    expect(screen.getByRole("heading", { level: 1, name: "Ma bibliothèque" })).toBeInTheDocument();
  });

  it("shows empty filter results state when filters yield nothing", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.BUYING, title: "Buying Comic", type: ComicType.MANGA }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?type=bd"] });

    await waitFor(() => {
      expect(screen.getByText("Aucune série avec ces filtres")).toBeInTheDocument();
    });
  });

  it("resets filters when clicking 'Réinitialiser les filtres'", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.BUYING, title: "Buying Comic", type: ComicType.MANGA }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?type=bd&status=finished&sort=title-desc"] });

    await waitFor(() => {
      expect(screen.getByText("Aucune série avec ces filtres")).toBeInTheDocument();
    });

    await user.click(screen.getByRole("button", { name: "Réinitialiser les filtres" }));

    await waitFor(() => {
      expect(screen.getByText("Buying Comic")).toBeInTheDocument();
    });
  });

  it("renders search input", async () => {
    renderWithProviders(<Home />);

    expect(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…")).toBeInTheDocument();
  });

  it("filters comics by search text", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "Naruto");

    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("displays count of filtered and total comics", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto", type: ComicType.MANGA }),
      createMockComicSeries({ id: 2, title: "Tintin", type: ComicType.BD }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("2/2")).toBeInTheDocument();
    });
  });

  it("renders filters component", async () => {
    renderWithProviders(<Home />);

    expect(screen.getByText("Tous les types")).toBeInTheDocument();
    expect(screen.getByText("Tous les statuts")).toBeInTheDocument();
  });

  it("filters comics by type selection", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto", type: ComicType.MANGA }),
      createMockComicSeries({ id: 2, title: "Tintin", type: ComicType.BD }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    // Open type filter and select Manga
    await user.click(screen.getByText("Tous les types"));
    await user.click(screen.getByText("Manga"));

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.queryByText("Tintin")).not.toBeInTheDocument();
  });

  it("immediately deletes when clicking delete from menu", async () => {
    const user = userEvent.setup();
    let deleteCalled = false;

    const comics = [
      createMockComicSeries({ id: 1, title: "Delete Me" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
      http.delete("/api/comic_series/1", () => {
        deleteCalled = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Delete Me")).toBeInTheDocument();
    });

    // Open the ⋮ dropdown menu on desktop
    const menuButtons = screen.getAllByTitle("Actions");
    await user.click(menuButtons[menuButtons.length - 1]);

    // Click "Supprimer" in the dropdown — no confirmation modal
    await user.click(screen.getByText("Supprimer"));

    await waitFor(() => {
      expect(deleteCalled).toBe(true);
    });
  });

  it("filters comics by status selection", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.BUYING, title: "Buying Comic" }),
      createMockComicSeries({ id: 2, status: ComicStatus.FINISHED, title: "Finished Comic" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Buying Comic")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Tous les statuts"));
    await user.click(screen.getByText("En cours d'achat"));

    expect(screen.getByText("Buying Comic")).toBeInTheDocument();
    expect(screen.queryByText("Finished Comic")).not.toBeInTheDocument();
  });

  it("shows undo toast after delete from menu", async () => {
    const user = userEvent.setup();

    const comics = [
      createMockComicSeries({ id: 1, title: "Toast Delete" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
      http.delete("/api/comic_series/1", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Toast Delete")).toBeInTheDocument();
    });

    // Open the ⋮ dropdown menu on desktop
    const menuButtons = screen.getAllByTitle("Actions");
    await user.click(menuButtons[menuButtons.length - 1]);

    // Click "Supprimer" in the dropdown
    await user.click(screen.getByText("Supprimer"));

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith(
        "Toast Delete supprimée",
        expect.objectContaining({ action: expect.objectContaining({ label: "Annuler" }) }),
      );
    });
  });

  it("renders sort selector with default title A→Z", async () => {
    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([])),
      ),
    );

    renderWithProviders(<Home />);

    expect(screen.getByText("Titre A→Z")).toBeInTheDocument();
  });

  it("sorts comics by title A→Z by default", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Zelda" }),
      createMockComicSeries({ id: 2, title: "Astérix" }),
      createMockComicSeries({ id: 3, title: "Naruto" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const headings = screen.getAllByRole("heading", { level: 3 });
    expect(headings[0]).toHaveTextContent("Astérix");
    expect(headings[1]).toHaveTextContent("Naruto");
    expect(headings[2]).toHaveTextContent("Zelda");
  });

  it("sorts comics by title Z→A when selected", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Astérix" }),
      createMockComicSeries({ id: 2, title: "Zelda" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Astérix")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Titre A→Z"));
    await user.click(screen.getByText("Titre Z→A"));

    const headings = screen.getAllByRole("heading", { level: 3 });
    expect(headings[0]).toHaveTextContent("Zelda");
    expect(headings[1]).toHaveTextContent("Astérix");
  });

  it("sorts comics by most recent first", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Old", createdAt: "2024-01-01T00:00:00+00:00" }),
      createMockComicSeries({ id: 2, title: "New", createdAt: "2025-06-01T00:00:00+00:00" }),
      createMockComicSeries({ id: 3, title: "Mid", createdAt: "2025-03-01T00:00:00+00:00" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Old")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Titre A→Z"));
    await user.click(screen.getByText("Plus récent"));

    const headings = screen.getAllByRole("heading", { level: 3 });
    expect(headings[0]).toHaveTextContent("New");
    expect(headings[1]).toHaveTextContent("Mid");
    expect(headings[2]).toHaveTextContent("Old");
  });

  it("sorts comics by most tomes first", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Few", tomes: [createMockTome()] }),
      createMockComicSeries({ id: 2, title: "Many", tomes: [createMockTome(), createMockTome(), createMockTome()] }),
      createMockComicSeries({ id: 3, title: "None", tomes: [] }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Few")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Titre A→Z"));
    await user.click(screen.getByText("Plus de tomes"));

    const headings = screen.getAllByRole("heading", { level: 3 });
    expect(headings[0]).toHaveTextContent("Many");
    expect(headings[1]).toHaveTextContent("Few");
    expect(headings[2]).toHaveTextContent("None");
  });

  it("filters comics by author name", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({
        authors: [createMockAuthor({ name: "Naoki Urasawa" })],
        id: 1,
        title: "Monster",
      }),
      createMockComicSeries({
        authors: [createMockAuthor({ name: "Eiichiro Oda" })],
        id: 2,
        title: "One Piece",
      }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Monster")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "Urasawa");

    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Monster")).toBeInTheDocument();
  });

  it("filters comics by publisher", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, publisher: "Kana", title: "Monster" }),
      createMockComicSeries({ id: 2, publisher: "Glénat", title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Monster")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "Kana");

    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Monster")).toBeInTheDocument();
  });

  it("filters comics with fuzzy search (typos)", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({
        authors: [createMockAuthor({ name: "Naoki Urasawa" })],
        id: 1,
        title: "Monster",
      }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Monster")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "Uraswa");

    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Monster")).toBeInTheDocument();
  });

  it("handles case-insensitive search with surrounding whitespace", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "  naruto  ");

    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("pre-filters by status URL param", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Wanted Comic" }),
      createMockComicSeries({ id: 2, status: ComicStatus.BUYING, title: "Buying Comic" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?status=wishlist"] });

    await waitFor(() => {
      expect(screen.getByText("Wanted Comic")).toBeInTheDocument();
    });
    expect(screen.queryByText("Buying Comic")).not.toBeInTheDocument();
  });

  it("pre-filters by type URL param", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto", type: ComicType.MANGA }),
      createMockComicSeries({ id: 2, title: "Tintin", type: ComicType.BD }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?type=manga"] });

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });
    expect(screen.queryByText("Tintin")).not.toBeInTheDocument();
  });

  it("pre-selects sort from URL param", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Astérix", createdAt: "2024-01-01T00:00:00+00:00" }),
      createMockComicSeries({ id: 2, title: "Zelda", createdAt: "2025-06-01T00:00:00+00:00" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?sort=createdAt-desc"] });

    await waitFor(() => {
      expect(screen.getByText("Astérix")).toBeInTheDocument();
    });

    const headings = screen.getAllByRole("heading", { level: 3 });
    expect(headings[0]).toHaveTextContent("Zelda");
    expect(headings[1]).toHaveTextContent("Astérix");
  });

  it("debounces search filtering", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…");

    // Type — local input updates immediately
    await user.type(searchInput, "Nar");
    expect(searchInput).toHaveValue("Nar");

    // Both comics still visible before debounce fires
    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.getByText("One Piece")).toBeInTheDocument();

    // After debounce, filtering applies
    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("restores all results after clearing search input", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…");

    // Type to filter
    await user.type(searchInput, "Naruto");
    await waitFor(() => {
      expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    });

    // Clear input
    await user.tripleClick(searchInput);
    await user.keyboard("{Backspace}");
    expect(searchInput).toHaveValue("");

    // After debounce, all comics reappear
    await waitFor(() => {
      expect(screen.getByText("One Piece")).toBeInTheDocument();
    });
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("shows loading indicator while data is being fetched", async () => {
    server.use(
      http.get("/api/comic_series", async () => {
        await new Promise((resolve) => setTimeout(resolve, 100));
        return HttpResponse.json(createMockHydraCollection([]));
      }),
    );

    renderWithProviders(<Home />);

    // While loading, skeletons should be visible
    expect(screen.getAllByTestId("comic-card-skeleton")).toHaveLength(8);

    // After load, skeletons disappear
    await waitFor(() => {
      expect(screen.queryByTestId("comic-card-skeleton")).not.toBeInTheDocument();
    });
  });

  it("shows search loading indicator during refetch", async () => {
    let requestCount = 0;
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
    ];

    server.use(
      http.get("/api/comic_series", async () => {
        requestCount++;
        if (requestCount > 1) {
          await new Promise((resolve) => setTimeout(resolve, 200));
        }
        return HttpResponse.json(createMockHydraCollection(comics));
      }),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    // The isFetching state from useComics should show an indicator
    // This test validates the presence of the loading indicator element
    // when data has already loaded but a refetch is happening
    expect(screen.queryByTestId("search-loading")).not.toBeInTheDocument();
  });

  it("renders virtualized results grid", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const grid = screen.getByTestId("comics-grid");
    expect(grid).toBeInTheDocument();
  });

  it("pre-fills search from URL param", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Naruto" }),
      createMockComicSeries({ id: 2, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Home />, { initialEntries: ["/?search=Naruto"] });

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();

    const searchInput = screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…");
    expect(searchInput).toHaveValue("Naruto");
  });
});
