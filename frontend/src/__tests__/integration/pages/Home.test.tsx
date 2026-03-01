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

  it("shows empty state when no comics", async () => {
    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([])),
      ),
    );

    renderWithProviders(<Home />);

    await waitFor(() => {
      expect(screen.getByText("Aucune série trouvée")).toBeInTheDocument();
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

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
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

  it("shows delete confirmation and fires API call", async () => {
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

    // Click delete button on the card
    await user.click(screen.getByTitle("Supprimer"));

    // Confirm modal should appear
    expect(screen.getByText(/Supprimer Delete Me/)).toBeInTheDocument();

    // Click the confirm button in the modal
    const confirmButton = screen.getByRole("button", { name: "Supprimer" });
    await user.click(confirmButton);

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

  it("shows success toast after delete confirmation", async () => {
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

    await user.click(screen.getByTitle("Supprimer"));
    const confirmButton = screen.getByRole("button", { name: "Supprimer" });
    await user.click(confirmButton);

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith("Toast Delete supprimée");
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

    expect(screen.getByText("Monster")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
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

    expect(screen.getByText("Monster")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
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

    expect(screen.getByText("Monster")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
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

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
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
