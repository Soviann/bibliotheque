import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import ShelfView from "../../../components/ShelfView";
import type { ComicSeries } from "../../../types/api";

const mockComic = (id: number, title: string, status: string): ComicSeries => ({
  "@id": `/api/comic_series/${id}`,
  amazonUrl: null,
  authors: [],
  boughtCount: 0,
  coveredCount: 0,
  coverImage: null,
  coverUrl: null,
  createdAt: "2026-01-01T00:00:00+00:00",
  defaultTomeBought: false,
  defaultTomeOnNas: false,
  defaultTomeRead: false,
  description: null,
  onNasCount: 0,
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
  status: status as ComicSeries["status"],
  title,
  tomesCount: 3,
  type: "manga",
  unboughtTomes: [],
  updatedAt: "2026-01-01T00:00:00+00:00",
});

describe("ShelfView", () => {
  it("groups comics by status into shelves", () => {
    const comics = [
      mockComic(1, "One Piece", "buying"),
      mockComic(2, "Naruto", "finished"),
      mockComic(3, "Bleach", "buying"),
    ];
    render(
      <MemoryRouter>
        <ShelfView comics={comics} onFilterByStatus={vi.fn()} />
      </MemoryRouter>,
    );
    expect(screen.getByText(/En cours d'achat/)).toBeInTheDocument();
    expect(screen.getByText(/Terminé/)).toBeInTheDocument();
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("places downloading shelf between buying and finished", () => {
    const comics = [
      mockComic(1, "One Piece", "buying"),
      mockComic(2, "Naruto", "finished"),
      mockComic(3, "Bleach", "downloading"),
    ];
    render(
      <MemoryRouter>
        <ShelfView comics={comics} onFilterByStatus={vi.fn()} />
      </MemoryRouter>,
    );
    const headings = screen.getAllByRole("heading", { level: 3 });
    const labels = headings.map((h) => h.textContent ?? "");
    const buyingIdx = labels.findIndex((l) => l.includes("En cours d'achat"));
    const downloadingIdx = labels.findIndex((l) =>
      l.includes("En cours de téléchargement"),
    );
    const finishedIdx = labels.findIndex((l) => l.includes("Terminé"));
    expect(buyingIdx).toBeGreaterThanOrEqual(0);
    expect(downloadingIdx).toBeGreaterThan(buyingIdx);
    expect(finishedIdx).toBeGreaterThan(downloadingIdx);
  });

  it("does not render empty status groups", () => {
    const comics = [mockComic(1, "One Piece", "buying")];
    render(
      <MemoryRouter>
        <ShelfView comics={comics} onFilterByStatus={vi.fn()} />
      </MemoryRouter>,
    );
    expect(screen.queryByText(/Terminé/)).not.toBeInTheDocument();
  });
});
