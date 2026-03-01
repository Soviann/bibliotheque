import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { toast } from "sonner";
import Home from "../../../pages/Home";
import {
  createMockComicSeries,
  createMockHydraCollection,
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

  it("shows loading state initially", () => {
    renderWithProviders(<Home />);

    expect(screen.getByText("Chargement…")).toBeInTheDocument();
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

    expect(screen.getByPlaceholderText("Rechercher une série…")).toBeInTheDocument();
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

    await user.type(screen.getByPlaceholderText("Rechercher une série…"), "Naruto");

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

    await user.type(screen.getByPlaceholderText("Rechercher une série…"), "  naruto  ");

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
  });
});
