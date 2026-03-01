import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import Wishlist from "../../../pages/Wishlist";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

describe("Wishlist", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("renders the page title", () => {
    renderWithProviders(<Wishlist />);

    expect(screen.getByText("Liste de souhaits")).toBeInTheDocument();
  });

  it("shows loading state initially", () => {
    renderWithProviders(<Wishlist />);

    expect(screen.getByText("Chargement…")).toBeInTheDocument();
  });

  it("shows wishlist comics only", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Wanted Comic" }),
      createMockComicSeries({ id: 2, status: ComicStatus.BUYING, title: "Buying Comic" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("Wanted Comic")).toBeInTheDocument();
    });
    expect(screen.queryByText("Buying Comic")).not.toBeInTheDocument();
  });

  it("shows empty state when no wishlist comics", async () => {
    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([])),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("Aucun souhait pour le moment")).toBeInTheDocument();
    });
  });

  it("displays wishlist count", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Wish 1" }),
      createMockComicSeries({ id: 2, status: ComicStatus.WISHLIST, title: "Wish 2" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("2 souhaits")).toBeInTheDocument();
    });
  });

  it("uses singular form for single wish", async () => {
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Solo Wish" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("1 souhait")).toBeInTheDocument();
    });
  });

  it("renders search input", () => {
    renderWithProviders(<Wishlist />);

    expect(screen.getByPlaceholderText("Rechercher…")).toBeInTheDocument();
  });

  it("hides status filter", () => {
    renderWithProviders(<Wishlist />);

    expect(screen.getByText("Tous les types")).toBeInTheDocument();
    expect(screen.queryByText("Tous les statuts")).not.toBeInTheDocument();
  });

  it("filters wishlist comics by search text", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Dragon Ball" }),
      createMockComicSeries({ id: 2, status: ComicStatus.WISHLIST, title: "One Piece" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("Dragon Ball")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher…"), "Dragon");

    expect(screen.getByText("Dragon Ball")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
  });

  it("filters wishlist comics by type selection", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, status: ComicStatus.WISHLIST, title: "Naruto", type: ComicType.MANGA }),
      createMockComicSeries({ id: 2, status: ComicStatus.WISHLIST, title: "Tintin", type: ComicType.BD }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Wishlist />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Tous les types"));
    await user.click(screen.getByText("Manga"));

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.queryByText("Tintin")).not.toBeInTheDocument();
  });
});
