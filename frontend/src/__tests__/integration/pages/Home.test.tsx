import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import Home from "../../../pages/Home";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

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
});
