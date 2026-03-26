import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it } from "vitest";
import HeroCarousel from "../../../components/HeroCarousel";
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
  unboughtTomeNumbers: [],
  updatedAt: "2026-01-01T00:00:00+00:00",
});

describe("HeroCarousel", () => {
  it("renders all comics as links", () => {
    const comics = [mockComic(1, "One Piece"), mockComic(2, "Naruto"), mockComic(3, "Bleach")];
    render(
      <MemoryRouter>
        <HeroCarousel comics={comics} />
      </MemoryRouter>,
    );
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.getByText("Bleach")).toBeInTheDocument();
  });

  it("renders section heading", () => {
    render(
      <MemoryRouter>
        <HeroCarousel comics={[mockComic(1, "Test")]} />
      </MemoryRouter>,
    );
    expect(screen.getByText("Récemment ajoutés")).toBeInTheDocument();
  });
});
