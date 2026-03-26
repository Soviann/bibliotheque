import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { setupServer } from "msw/node";
import EnrichmentReview from "../../../pages/EnrichmentReview";
import { renderWithProviders } from "../../helpers/test-utils";

const proposals = [
  {
    "@id": "/enrichment_proposals/1",
    comicSeries: { "@id": "/comic_series/10", id: 10, title: "One Piece" },
    confidence: "high",
    createdAt: "2026-01-01T00:00:00+00:00",
    currentValue: null,
    field: "description",
    id: 1,
    proposedValue: "Un manga d'aventure",
    reviewedAt: null,
    source: "AniList",
    status: "pending",
  },
  {
    "@id": "/enrichment_proposals/2",
    comicSeries: { "@id": "/comic_series/10", id: 10, title: "One Piece" },
    confidence: "medium",
    createdAt: "2026-01-01T00:00:00+00:00",
    currentValue: null,
    field: "cover",
    id: 2,
    proposedValue: "https://example.com/cover.jpg",
    reviewedAt: null,
    source: "GoogleBooks",
    status: "pending",
  },
  {
    "@id": "/enrichment_proposals/3",
    comicSeries: { "@id": "/comic_series/20", id: 20, title: "Astérix" },
    confidence: "low",
    createdAt: "2026-01-01T00:00:00+00:00",
    currentValue: "Ancien éditeur",
    field: "publisher",
    id: 3,
    proposedValue: "Hachette",
    reviewedAt: null,
    source: "GoogleBooks",
    status: "pending",
  },
  {
    "@id": "/enrichment_proposals/4",
    comicSeries: { "@id": "/comic_series/30", id: 30, title: "Batman" },
    confidence: "high",
    createdAt: "2026-01-01T00:00:00+00:00",
    currentValue: null,
    field: "authors",
    id: 4,
    proposedValue: ["Bob Kane"],
    reviewedAt: null,
    source: "Wikipedia",
    status: "pending",
  },
];

const server = setupServer(
  http.get("*/api/enrichment_proposals", () =>
    HttpResponse.json({
      "@context": "/api/contexts/EnrichmentProposal",
      "@id": "/enrichment_proposals",
      "@type": "Collection",
      member: proposals,
      totalItems: proposals.length,
    }),
  ),
);

beforeAll(() => server.listen());
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

describe("EnrichmentReview", () => {
  it("renders all series groups when no filters applied", async () => {
    renderWithProviders(<EnrichmentReview />);

    expect(await screen.findByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Astérix")).toBeInTheDocument();
    expect(screen.getByText("Batman")).toBeInTheDocument();
  });

  it("filters proposals by series name search", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    await user.type(screen.getByPlaceholderText("Rechercher une série…"), "astérix");

    expect(screen.getByText("Astérix")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    expect(screen.queryByText("Batman")).not.toBeInTheDocument();
  });

  it("filters proposals by field", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    await user.click(screen.getByRole("button", { name: "Champ" }));
    await user.click(screen.getByRole("option", { name: "Couverture" }));

    // Only One Piece has a cover proposal
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.queryByText("Astérix")).not.toBeInTheDocument();
    expect(screen.queryByText("Batman")).not.toBeInTheDocument();
  });

  it("filters proposals by confidence level", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    await user.click(screen.getByRole("button", { name: "Confiance" }));
    await user.click(screen.getByRole("option", { name: "Basse" }));

    // Only Astérix has low confidence
    expect(screen.getByText("Astérix")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    expect(screen.queryByText("Batman")).not.toBeInTheDocument();
  });

  it("filters proposals by source", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    await user.click(screen.getByRole("button", { name: "Source" }));
    await user.click(screen.getByRole("option", { name: "Wikipedia" }));

    // Only Batman has Wikipedia source
    expect(screen.getByText("Batman")).toBeInTheDocument();
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    expect(screen.queryByText("Astérix")).not.toBeInTheDocument();
  });

  it("combines multiple filters", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    // Filter by source GoogleBooks
    await user.click(screen.getByRole("button", { name: "Source" }));
    await user.click(screen.getByRole("option", { name: "GoogleBooks" }));

    // Both One Piece and Astérix have GoogleBooks proposals
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Astérix")).toBeInTheDocument();
    expect(screen.queryByText("Batman")).not.toBeInTheDocument();

    // Add confidence filter: high
    await user.click(screen.getByRole("button", { name: "Confiance" }));
    await user.click(screen.getByRole("option", { name: "Haute" }));

    // GoogleBooks + high = impossible — no proposals match both
    expect(screen.queryByText("One Piece")).not.toBeInTheDocument();
    expect(screen.queryByText("Astérix")).not.toBeInTheDocument();
  });

  it("shows result count", async () => {
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    expect(screen.getByText(/4 propositions/)).toBeInTheDocument();
  });

  it("filters only proposals within a series, keeping other proposals visible", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    // Filter by field: description — One Piece has 2 proposals but only 1 is description
    await user.click(screen.getByRole("button", { name: "Champ" }));
    await user.click(screen.getByRole("option", { name: "Description" }));

    // One Piece should show but only 1 proposal card
    const seriesSection = screen.getByText("One Piece").closest("div[class*='rounded-xl']")!;
    const proposals = within(seriesSection).getAllByTitle("Accepter");
    expect(proposals).toHaveLength(1);
  });

  it("shows empty state when filters match nothing", async () => {
    const user = userEvent.setup();
    renderWithProviders(<EnrichmentReview />);

    await screen.findByText("One Piece");

    await user.type(screen.getByPlaceholderText("Rechercher une série…"), "zzzznotfound");

    expect(screen.getByText("Aucune proposition en attente")).toBeInTheDocument();
  });
});
