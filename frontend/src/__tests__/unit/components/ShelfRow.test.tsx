import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it } from "vitest";
import ShelfRow from "../../../components/ShelfRow";
import type { ComicSeries } from "../../../types/api";

const mockComic = (id: number, title: string): ComicSeries => ({
  "@id": `/api/comic_series/${id}`,
  amazonUrl: null,
  authors: [],
  boughtCount: 0,
  coveredCount: 0,
  coverImage: null,
  coverUrl: null,
  createdAt: "2026-01-01T00:00:00+00:00",
  defaultTomeBought: false,
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
  notInterestedBuy: false,
  notInterestedNas: false,
  publishedDate: null,
  publisher: null,
  readCount: 0,
  status: "buying",
  title,
  tomesCount: 3,
  type: "manga",
  unboughtTomes: [],
  updatedAt: "2026-01-01T00:00:00+00:00",
});

describe("ShelfRow", () => {
  it("renders title with count and comics", () => {
    const comics = [mockComic(1, "One Piece"), mockComic(2, "Naruto")];
    render(
      <MemoryRouter>
        <ShelfRow comics={comics} onSeeAll={() => {}} title="En cours" />
      </MemoryRouter>,
    );
    expect(screen.getByText(/En cours/)).toBeInTheDocument();
    expect(screen.getByText("(2)")).toBeInTheDocument();
    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("renders see-all button", () => {
    render(
      <MemoryRouter>
        <ShelfRow comics={[mockComic(1, "Test")]} onSeeAll={() => {}} title="Terminé" />
      </MemoryRouter>,
    );
    expect(screen.getByText("Tout voir")).toBeInTheDocument();
  });

  it("does not render when comics is empty", () => {
    const { container } = render(
      <MemoryRouter>
        <ShelfRow comics={[]} onSeeAll={() => {}} title="Vide" />
      </MemoryRouter>,
    );
    expect(container.firstChild).toBeNull();
  });
});
