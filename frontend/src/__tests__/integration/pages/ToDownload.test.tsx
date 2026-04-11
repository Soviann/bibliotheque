import { screen } from "@testing-library/react";
import { setupServer } from "msw/node";
import { afterAll, afterEach, beforeAll, describe, expect, it } from "vitest";
import ToDownload from "../../../pages/ToDownload";
import type { ComicSeries } from "../../../types/api";
import { queryKeys } from "../../../queryKeys";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import {
  createTestQueryClient,
  renderWithProviders,
} from "../../helpers/test-utils";

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: "bypass" }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderWithComics(comics: ComicSeries[]) {
  const queryClient = createTestQueryClient();
  queryClient.setQueryData(
    queryKeys.comics.all,
    createMockHydraCollection(comics),
  );
  return renderWithProviders(<ToDownload />, {
    initialEntries: ["/to-download"],
    queryClient,
  });
}

describe("ToDownload", () => {
  it("shows empty state when no series to download", () => {
    renderWithComics([]);
    expect(screen.getByText("Rien à télécharger")).toBeInTheDocument();
  });

  it("shows downloading series with tome badges labelled for download", () => {
    const series = createMockComicSeries({
      id: 1,
      status: "downloading",
      title: "Naruto",
      type: "manga",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 3 }],
    });
    renderWithComics([series]);

    expect(
      screen.getByRole("button", {
        name: "Marquer le tome 3 comme téléchargé",
      }),
    ).toBeInTheDocument();
  });

  it("excludes buying series", () => {
    const series = createMockComicSeries({
      status: "buying",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);
    expect(screen.getByText("Rien à télécharger")).toBeInTheDocument();
  });

  it("excludes downloading one-shots", () => {
    const series = createMockComicSeries({
      isOneShot: true,
      status: "downloading",
      unboughtTomes: [{ id: 10, isHorsSerie: false, number: 1 }],
    });
    renderWithComics([series]);
    expect(screen.getByText("Rien à télécharger")).toBeInTheDocument();
  });
});
