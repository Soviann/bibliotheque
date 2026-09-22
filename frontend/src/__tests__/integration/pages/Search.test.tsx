import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import Search from "../../../pages/Search";
import { ComicType } from "../../../types/enums";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

describe("Search Page", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders search header and input", () => {
    renderWithProviders(<Search />);

    expect(screen.getByText("Recherche Avancée")).toBeInTheDocument();
    expect(
      screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur, ISBN…"),
    ).toBeInTheDocument();
  });

  it("filters comics by search query", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Berserk" }),
      createMockComicSeries({ id: 2, title: "Vinland Saga" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
      expect(screen.getByText("Vinland Saga")).toBeInTheDocument();
    });

    const input = screen.getByPlaceholderText(
      "Rechercher par titre, auteur, éditeur, ISBN…",
    );
    await user.type(input, "Berserk");

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
      expect(screen.queryByText("Vinland Saga")).not.toBeInTheDocument();
    });
  });

  it("filters comics by type pills", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "Berserk",
        type: ComicType.MANGA,
      }),
      createMockComicSeries({
        id: 2,
        title: "Tintin",
        type: ComicType.BD,
      }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
      expect(screen.getByText("Tintin")).toBeInTheDocument();
    });

    const bdPill = screen.getByRole("button", { name: "BD" });
    await user.click(bdPill);

    await waitFor(() => {
      expect(screen.getByText("Tintin")).toBeInTheDocument();
      expect(screen.queryByText("Berserk")).not.toBeInTheDocument();
    });
  });

  it("displays ✕ on unmonitored axes (notInterestedBuy / notInterestedNas)", async () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        notInterestedBuy: true,
        notInterestedNas: true,
        title: "Unmonitored Series",
      }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    await waitFor(() => {
      expect(screen.getByText("Unmonitored Series")).toBeInTheDocument();
    });

    expect(screen.getByText("Achat: ✕")).toBeInTheDocument();
    expect(screen.getByText("NAS: ✕")).toBeInTheDocument();
  });

  it("shows empty state when no results match", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Berserk" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });

    const input = screen.getByPlaceholderText(
      "Rechercher par titre, auteur, éditeur, ISBN…",
    );
    await user.type(input, "ZzzzNonExistent");

    await waitFor(() => {
      expect(screen.getByText("Aucun résultat trouvé")).toBeInTheDocument();
    });
  });

  it("supports clearing search via clear button", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Berserk" }),
      createMockComicSeries({ id: 2, title: "Vinland Saga" }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    const input = screen.getByPlaceholderText(
      "Rechercher par titre, auteur, éditeur, ISBN…",
    );
    await user.type(input, "Berserk");

    await waitFor(() => {
      expect(screen.queryByText("Vinland Saga")).not.toBeInTheDocument();
    });

    const clearBtn = screen.getByLabelText("Effacer la recherche");
    await user.click(clearBtn);

    await waitFor(() => {
      expect(screen.getByText("Vinland Saga")).toBeInTheDocument();
    });
  });

  it("filters by specific scope (author only)", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({
        authors: [{ "@id": "/api/authors/1", followedForNewSeries: false, id: 1, name: "Urasawa" }],
        id: 1,
        title: "Monster",
      }),
      createMockComicSeries({
        authors: [{ "@id": "/api/authors/2", followedForNewSeries: false, id: 2, name: "Oda" }],
        id: 2,
        title: "Urasawa Fan Club",
      }),
    ];

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection(comics)),
      ),
    );

    renderWithProviders(<Search />);

    await waitFor(() => {
      expect(screen.getByText("Monster")).toBeInTheDocument();
    });

    // Toggle author scope
    const authorScopeBtn = screen.getByRole("button", { name: "Auteur / Dessinateur" });
    await user.click(authorScopeBtn);

    const input = screen.getByPlaceholderText(
      "Rechercher par titre, auteur, éditeur, ISBN…",
    );
    await user.type(input, "Urasawa");

    await waitFor(() => {
      expect(screen.getByText("Monster")).toBeInTheDocument();
      // Should exclude "Urasawa Fan Club" because scope is restricted to author
      expect(screen.queryByText("Urasawa Fan Club")).not.toBeInTheDocument();
    });
  });
});
