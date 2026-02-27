import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it } from "vitest";
import ComicCard from "../../components/ComicCard";
import type { ComicSeries } from "../../types/api";

function makeComic(overrides: Partial<ComicSeries> = {}): ComicSeries {
  return {
    "@id": "/api/comic_series/1",
    authors: [{ "@id": "/api/authors/1", id: 1, name: "Eiichiro Oda" }],
    coverImage: null,
    coverUrl: "https://example.com/cover.jpg",
    createdAt: "2024-01-01",
    description: null,
    id: 1,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    publishedDate: null,
    publisher: null,
    status: "buying",
    title: "One Piece",
    tomes: [],
    type: "manga",
    updatedAt: "2024-01-01",
    ...overrides,
  };
}

describe("ComicCard", () => {
  it("renders the title", () => {
    render(
      <MemoryRouter>
        <ComicCard comic={makeComic()} />
      </MemoryRouter>,
    );

    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("renders cover image when coverUrl is set", () => {
    render(
      <MemoryRouter>
        <ComicCard comic={makeComic()} />
      </MemoryRouter>,
    );

    const img = screen.getByAltText("One Piece");
    expect(img).toHaveAttribute("src", "https://example.com/cover.jpg");
  });

  it("renders placeholder when no cover", () => {
    render(
      <MemoryRouter>
        <ComicCard comic={makeComic({ coverImage: null, coverUrl: null })} />
      </MemoryRouter>,
    );

    const img = screen.getByAltText("One Piece");
    expect(img).toHaveAttribute("src", "/placeholder-cover.png");
  });

  it("renders type and tome count", () => {
    render(
      <MemoryRouter>
        <ComicCard
          comic={makeComic({
            tomes: [
              {
                "@id": "/api/tomes/1",
                bought: true,
                createdAt: "2024-01-01",
                downloaded: false,
                id: 1,
                isbn: null,
                number: 1,
                onNas: false,
                read: false,
                title: null,
                updatedAt: "2024-01-01",
              },
            ],
          })}
        />
      </MemoryRouter>,
    );

    expect(screen.getByText(/Manga/)).toBeInTheDocument();
    expect(screen.getByText(/1 t\./)).toBeInTheDocument();
  });

  it("links to comic detail page", () => {
    render(
      <MemoryRouter>
        <ComicCard comic={makeComic()} />
      </MemoryRouter>,
    );

    const link = screen.getByRole("link");
    expect(link).toHaveAttribute("href", "/comic/1");
  });

  it("renders edit button", () => {
    render(
      <MemoryRouter>
        <ComicCard comic={makeComic()} />
      </MemoryRouter>,
    );

    expect(screen.getByTitle("Modifier")).toBeInTheDocument();
  });
});
