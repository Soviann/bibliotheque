import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import LookupCandidateCard from "../../../components/LookupCandidateCard";
import type { LookupCandidate } from "../../../types/api";
import { renderWithProviders } from "../../helpers/test-utils";

function buildCandidate(overrides: Partial<LookupCandidate> = {}): LookupCandidate {
  return {
    authors: null,
    description: null,
    isbn: null,
    isOneShot: null,
    latestPublishedIssue: null,
    publishedDate: null,
    publisher: null,
    thumbnail: null,
    title: null,
    tomeEnd: null,
    tomeNumber: null,
    ...overrides,
  };
}

describe("LookupCandidateCard", () => {
  it("renders title and authors text", () => {
    const candidate = buildCandidate({
      authors: "Akira Toriyama",
      title: "Dragon Ball",
    });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.getByText("Dragon Ball")).toBeInTheDocument();
    expect(screen.getByText("Akira Toriyama")).toBeInTheDocument();
  });

  it("renders publisher, year, and volume chips", () => {
    const candidate = buildCandidate({
      publishedDate: "2000-05-12",
      publisher: "Dargaud",
      tomeEnd: 6,
    });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.getByText("Dargaud")).toBeInTheDocument();
    expect(screen.getByText("2000")).toBeInTheDocument();
    expect(screen.getByText("6 tomes")).toBeInTheDocument();
  });

  it("omits publisher chip when publisher is null", () => {
    const candidate = buildCandidate({ publisher: null });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.queryByText("Dargaud")).not.toBeInTheDocument();
  });

  it("renders One-shot chip and no tomes chip when isOneShot is true", () => {
    const candidate = buildCandidate({ isOneShot: true, tomeEnd: 1 });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.getByText("One-shot")).toBeInTheDocument();
    expect(screen.queryByText(/tomes/i)).not.toBeInTheDocument();
  });

  it("shows plus button; clicking it shows moins and does not call onSelect", async () => {
    const user = userEvent.setup();
    const candidate = buildCandidate({
      description: "Une longue description de test pour vérifier le toggle.",
    });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    const plusButton = screen.getByText("plus");
    expect(plusButton).toBeInTheDocument();

    await user.click(plusButton);

    expect(screen.getByText("moins")).toBeInTheDocument();
    expect(onSelect).not.toHaveBeenCalled();
  });

  it("calls onSelect when the card title is clicked", async () => {
    const user = userEvent.setup();
    const candidate = buildCandidate({ title: "Naruto" });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    await user.click(screen.getByText("Naruto"));

    expect(onSelect).toHaveBeenCalledTimes(1);
  });

  it("calls onSelect when Enter or Space is pressed on the focused card", async () => {
    const user = userEvent.setup();
    const candidate = buildCandidate({ title: "Akira" });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    await user.tab();
    expect(screen.getByRole("button", { name: /Akira/i })).toHaveFocus();

    await user.keyboard("{Enter}");
    await user.keyboard(" ");

    expect(onSelect).toHaveBeenCalledTimes(2);
  });

  it("renders an external thumbnail as a plain img without crossOrigin", () => {
    const candidate = buildCandidate({
      thumbnail: "https://books.google.com/cover.jpg",
      title: "One Piece",
    });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    const img = screen.getByRole("img");
    expect(img).toHaveAttribute("src", "https://books.google.com/cover.jpg");
    // External lookup thumbnails must load without CORS — no crossOrigin.
    expect(img).not.toHaveAttribute("crossorigin");
  });

  it("shows placeholder and no img element when thumbnail is null", () => {
    const candidate = buildCandidate({ thumbnail: null });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.getByText("?")).toBeInTheDocument();
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
  });

  it("does not show plus button when description is null", () => {
    const candidate = buildCandidate({ description: null });
    const onSelect = vi.fn();

    renderWithProviders(
      <LookupCandidateCard candidate={candidate} onSelect={onSelect} />,
    );

    expect(screen.queryByText(/plus/i)).not.toBeInTheDocument();
  });
});
