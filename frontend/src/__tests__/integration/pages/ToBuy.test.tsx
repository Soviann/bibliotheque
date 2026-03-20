import { screen } from "@testing-library/react";
import { queryKeys } from "../../../queryKeys";
import type { ComicSeries } from "../../../types/api";
import { createTestQueryClient, renderWithProviders } from "../../helpers/test-utils";
import ToBuy from "../../../pages/ToBuy";

function makeSeries(id: number, title: string, overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": `/api/comics/${id}`,
    authors: [],
    boughtCount: 0,
    coveredCount: 0,
    coverImage: null,
    coverUrl: null,
    createdAt: "2024-01-01T00:00:00+00:00",
    defaultTomeBought: true,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: null,
    downloadedCount: 0,
    id,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    latestPublishedIssueUpdatedAt: null,
    maxTomeNumber: null,
    publishedDate: null,
    publisher: null,
    readCount: 0,
    status: "buying",
    title,
    tomesCount: 0,
    type: "manga",
    unboughtTomeNumbers: [],
    updatedAt: "2024-01-01T00:00:00+00:00",
    ...overrides,
  };
}

function renderWithComics(comics: ComicSeries[]) {
  const queryClient = createTestQueryClient();
  queryClient.setQueryData(queryKeys.comics.all, {
    "@context": "/api/contexts/ComicSeries",
    "@id": "/api/comics",
    "@type": "Collection",
    member: comics,
    totalItems: comics.length,
  });
  return renderWithProviders(<ToBuy />, { initialEntries: ["/to-buy"], queryClient });
}

describe("ToBuy", () => {
  it("shows empty state when no series to buy", () => {
    renderWithComics([]);
    expect(screen.getByText("Rien à acheter")).toBeInTheDocument();
  });

  it("shows buying series with unbought tomes", () => {
    const series = makeSeries(1, "One Piece", {
      unboughtTomeNumbers: [2, 3],
    });
    renderWithComics([series]);
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Prochain : T.2, T.3")).toBeInTheDocument();
  });

  it("excludes finished series", () => {
    const series = makeSeries(1, "Naruto", {
      status: "finished",
      unboughtTomeNumbers: [1],
    });
    renderWithComics([series]);
    expect(screen.queryByText("Naruto")).not.toBeInTheDocument();
    expect(screen.getByText("Rien à acheter")).toBeInTheDocument();
  });

  it("excludes one-shots", () => {
    const series = makeSeries(1, "Akira", {
      isOneShot: true,
      unboughtTomeNumbers: [1],
    });
    renderWithComics([series]);
    expect(screen.queryByText("Akira")).not.toBeInTheDocument();
  });

  it("excludes series with all tomes bought", () => {
    const series = makeSeries(1, "Bleach", {
      unboughtTomeNumbers: [],
    });
    renderWithComics([series]);
    expect(screen.queryByText("Bleach")).not.toBeInTheDocument();
  });

  it("sorts series by title", () => {
    const series = [
      makeSeries(1, "Zetman", { unboughtTomeNumbers: [1] }),
      makeSeries(2, "Akira Toriyama", { unboughtTomeNumbers: [1] }),
    ];
    renderWithComics(series);
    const cards = screen.getAllByRole("link");
    expect(cards[0]).toHaveTextContent("Akira Toriyama");
    expect(cards[1]).toHaveTextContent("Zetman");
  });

  it("shows single tome as 'Prochain : T.X'", () => {
    const series = makeSeries(1, "Solo", {
      unboughtTomeNumbers: [2],
    });
    renderWithComics([series]);
    expect(screen.getByText("Prochain : T.2")).toBeInTheDocument();
  });
});
