import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { setupServer } from "msw/node";
import { afterAll, afterEach, beforeAll, describe, expect, it } from "vitest";
import ToBuy from "../../../pages/ToBuy";
import { queryKeys } from "../../../queryKeys";
import { createMockComicSeries, createMockHydraCollection } from "../../helpers/factories";
import { createTestQueryClient, renderWithProviders } from "../../helpers/test-utils";

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: "bypass" }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderWithComics(comics: ComicSeries[]) {
  const queryClient = createTestQueryClient();
  queryClient.setQueryData(queryKeys.comics.all, createMockHydraCollection(comics));
  return renderWithProviders(<ToBuy />, { initialEntries: ["/to-buy"], queryClient });
}

describe("ToBuy", () => {
  it("shows empty state when no series to buy", () => {
    renderWithComics([]);
    expect(screen.getByText("Rien à acheter")).toBeInTheDocument();
  });

  it("groups series by type with section headers", () => {
    const manga = createMockComicSeries({
      id: 1,
      title: "One Piece",
      type: "manga",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    const bd = createMockComicSeries({
      id: 2,
      title: "Astérix",
      type: "bd",
      unboughtTomes: [{ id: 20, isHorsSerie: false, number: 2 }],
    });
    renderWithComics([manga, bd]);

    expect(screen.getByText("Manga")).toBeInTheDocument();
    expect(screen.getByText("BD")).toBeInTheDocument();
  });

  it("shows individual tome badges with numbers", () => {
    const series = createMockComicSeries({
      id: 1,
      title: "Naruto",
      type: "manga",
      unboughtTomes: [
        { id: 10, isHorsSerie: false, number: 3 },
        { id: 20, isHorsSerie: false, number: 5 },
      ],
    });
    renderWithComics([series]);

    expect(screen.getByRole("button", { name: "Marquer le tome 3 comme acheté" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Marquer le tome 5 comme acheté" })).toBeInTheDocument();
  });

  it("displays hors-série badges with HS prefix", () => {
    const series = createMockComicSeries({
      id: 1,
      title: "Dragon Ball",
      type: "manga",
      unboughtTomes: [
        { id: 10, isHorsSerie: true, number: 1 },
      ],
    });
    renderWithComics([series]);

    expect(screen.getByRole("button", { name: "Marquer le tome HS 1 comme acheté" })).toBeInTheDocument();
  });

  it("sorts series alphabetically within each group", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "Zetman",
        type: "manga",
        unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
      }),
      createMockComicSeries({
        id: 2,
        title: "Akira",
        type: "manga",
        unboughtTomes: [{ id: 20, isHorsSerie: false, number: 1 }],
      }),
    ];
    renderWithComics(comics);

    const mangaSection = screen.getByTestId("type-section-manga");
    const titles = within(mangaSection).getAllByTestId("series-title");
    expect(titles[0]).toHaveTextContent("Akira");
    expect(titles[1]).toHaveTextContent("Zetman");
  });

  it("shows eye icon link to detail page", () => {
    const series = createMockComicSeries({
      id: 42,
      title: "Bleach",
      type: "manga",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);

    const detailLink = screen.getByRole("link", { name: /détail/i });
    expect(detailLink).toHaveAttribute("href", "/comic/42");
  });

  it("shows Amazon link when amazonUrl exists", () => {
    const series = createMockComicSeries({
      amazonUrl: "https://amazon.fr/bleach",
      id: 1,
      title: "Bleach",
      type: "manga",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);

    const amazonLink = screen.getByRole("link", { name: /amazon/i });
    expect(amazonLink).toHaveAttribute("href", "https://amazon.fr/bleach");
  });

  it("hides Amazon link when no amazonUrl", () => {
    const series = createMockComicSeries({
      amazonUrl: null,
      id: 1,
      title: "Bleach",
      type: "manga",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);

    expect(screen.queryByRole("link", { name: /amazon/i })).not.toBeInTheDocument();
  });

  it("calls PATCH endpoint on badge click", async () => {
    const user = userEvent.setup();
    let patchCalled = false;
    const series = createMockComicSeries({
      id: 1,
      title: "Solo",
      type: "manga",
      unboughtTomes: [
        { id: 10, isHorsSerie: false, number: 2 },
        { id: 20, isHorsSerie: false, number: 3 },
      ],
    });

    server.use(
      http.patch("*/api/tomes/10", () => {
        patchCalled = true;
        return HttpResponse.json({ id: 10, bought: true });
      }),
      http.get("*/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([{
          ...series,
          unboughtTomes: [{ id: 20, isHorsSerie: false, number: 3 }],
        }]))),
    );

    renderWithComics([series]);

    const badge = screen.getByRole("button", { name: "Marquer le tome 2 comme acheté" });
    await user.click(badge);

    await waitFor(() => {
      expect(patchCalled).toBe(true);
    });
  });

  it("excludes finished series", () => {
    const series = createMockComicSeries({
      status: "finished",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);
    expect(screen.getByText("Rien à acheter")).toBeInTheDocument();
  });

  it("excludes one-shots", () => {
    const series = createMockComicSeries({
      isOneShot: true,
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);
    expect(screen.getByText("Rien à acheter")).toBeInTheDocument();
  });
});
