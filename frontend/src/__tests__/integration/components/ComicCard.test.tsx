import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ComicCard from "../../../components/ComicCard";
import { createMockComicSeries } from "../../helpers/factories";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

describe("ComicCard", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders comic title and type", () => {
    const comic = createMockComicSeries({
      title: "Naruto",
      type: ComicType.MANGA,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.getByText(/Manga/)).toBeInTheDocument();
  });

  it("renders cover from coverImage when available (local first)", () => {
    const comic = createMockComicSeries({
      coverImage: "naruto.webp",
      coverUrl: "https://example.com/cover.jpg",
      title: "Naruto",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("Naruto");
    expect(img).toHaveAttribute("src", "/media/cache/cover_thumbnail/uploads/covers/naruto.webp");
  });

  it("falls back to coverUrl when coverImage is null", () => {
    const comic = createMockComicSeries({
      coverImage: null,
      coverUrl: "https://example.com/cover.jpg",
      title: "One Piece",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("One Piece");
    expect(img).toHaveAttribute("src", "https://example.com/cover.jpg");
  });

  it("shows type-specific placeholder when no cover", () => {
    const comic = createMockComicSeries({
      coverImage: null,
      coverUrl: null,
      title: "No Cover",
      type: ComicType.BD,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("No Cover");
    expect(img).toHaveAttribute("src", "/placeholder-bd.jpg");
  });

  it("links to comic detail page", () => {
    const comic = createMockComicSeries({ id: 42, title: "Dragon Ball" });

    renderWithProviders(<ComicCard comic={comic} />);

    const link = screen.getByRole("link");
    expect(link).toHaveAttribute("href", "/comic/42");
  });

  it("displays tome count for non-oneshot series", () => {
    const comic = createMockComicSeries({
      isOneShot: false,
      title: "Bleach",
      tomesCount: 3,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText(/3 t\./)).toBeInTheDocument();
  });

  it("does not display tome count for oneshot", () => {
    const comic = createMockComicSeries({
      isOneShot: true,
      title: "Akira",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByText(/t\./)).not.toBeInTheDocument();
  });

  it("shows the ⋮ menu button with aria-label", () => {
    const comic = createMockComicSeries({ title: "Test" });

    renderWithProviders(<ComicCard comic={comic} onDelete={vi.fn()} />);

    const buttons = screen.getAllByLabelText("Actions");
    expect(buttons.length).toBeGreaterThanOrEqual(1);
  });

  it("does not show ⋮ button when no action callbacks are provided", () => {
    const comic = createMockComicSeries({ title: "Test" });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByTitle("Actions")).not.toBeInTheDocument();
  });

  it("calls onMenuOpen when mobile ⋮ button is clicked", async () => {
    const user = userEvent.setup();
    const comic = createMockComicSeries({ title: "Test" });
    const onMenuOpen = vi.fn();

    renderWithProviders(
      <ComicCard comic={comic} onDelete={vi.fn()} onMenuOpen={onMenuOpen} />,
    );

    // Click the mobile button (lg:hidden)
    const buttons = screen.getAllByTitle("Actions");
    // The first one is the mobile button
    await user.click(buttons[0]);

    expect(onMenuOpen).toHaveBeenCalledWith(comic);
  });

  it("shows desktop dropdown with edit and delete options", async () => {
    const user = userEvent.setup();
    const comic = createMockComicSeries({ id: 5, title: "Test" });
    const onDelete = vi.fn();

    renderWithProviders(<ComicCard comic={comic} onDelete={onDelete} />);

    // Click the desktop menu button (hidden lg:block)
    const buttons = screen.getAllByTitle("Actions");
    // The last one is the desktop Headless UI MenuButton
    await user.click(buttons[buttons.length - 1]);

    expect(screen.getByText("Modifier")).toBeInTheDocument();
    expect(screen.getByText("Supprimer")).toBeInTheDocument();
  });

  it("calls onDelete from desktop dropdown", async () => {
    const user = userEvent.setup();
    const comic = createMockComicSeries({ id: 5, title: "Test" });
    const onDelete = vi.fn();

    renderWithProviders(<ComicCard comic={comic} onDelete={onDelete} />);

    const buttons = screen.getAllByTitle("Actions");
    await user.click(buttons[buttons.length - 1]);
    await user.click(screen.getByText("Supprimer"));

    expect(onDelete).toHaveBeenCalledWith(comic);
  });

  it("shows progress text when latestPublishedIssue is set", () => {
    const comic = createMockComicSeries({
      boughtCount: 2,
      coveredCount: 3,
      isOneShot: false,
      latestPublishedIssue: 24,
      title: "Naruto",
      tomesCount: 3,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText(/2\s*\/\s*24/)).toBeInTheDocument();
  });

  it("shows all three stat counters", () => {
    const comic = createMockComicSeries({
      boughtCount: 2,
      coveredCount: 3,
      onNasCount: 1,
      isOneShot: false,
      latestPublishedIssue: 10,
      readCount: 1,
      title: "One Piece",
      tomesCount: 3,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByTitle("Achetés")).toHaveTextContent("2/10");
    expect(screen.getByTitle("Lus")).toHaveTextContent("1/10");
    expect(screen.getByTitle("Sur NAS")).toHaveTextContent("1/10");
  });

  it("uses tome count as total when latestPublishedIssue is null", () => {
    const comic = createMockComicSeries({
      boughtCount: 1,
      coveredCount: 2,
      isOneShot: false,
      latestPublishedIssue: null,
      title: "Unknown Total",
      tomesCount: 2,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByTitle("Achetés")).toHaveTextContent("1/2");
  });

  it("uses coveredCount when it exceeds latestPublishedIssue", () => {
    const comic = createMockComicSeries({
      boughtCount: 3,
      coveredCount: 3,
      isOneShot: false,
      latestPublishedIssue: 2,
      title: "Overflow",
      tomesCount: 3,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByTitle("Achetés")).toHaveTextContent("3/3");
  });

  it("does not show stats for oneshot series", () => {
    const comic = createMockComicSeries({
      isOneShot: true,
      latestPublishedIssue: 1,
      title: "Single",
      tomesCount: 1,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByTitle("Achetés")).not.toBeInTheDocument();
  });

  it("does not show stats when no tomes", () => {
    const comic = createMockComicSeries({
      isOneShot: false,
      latestPublishedIssue: null,
      title: "Empty",
      tomesCount: 0,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByTitle("Achetés")).not.toBeInTheDocument();
  });

  it("shows new release badge when new tomes detected recently", () => {
    const recentDate = new Date();
    recentDate.setDate(recentDate.getDate() - 2);

    const comic = createMockComicSeries({
      latestPublishedIssue: 10,
      latestPublishedIssueUpdatedAt: recentDate.toISOString(),
      maxTomeNumber: 2,
      status: ComicStatus.BUYING,
      title: "Naruto",
      tomesCount: 2,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText("Nouveau")).toBeInTheDocument();
  });

  it("shows 'En attente de synchronisation' on offline card", () => {
    const comic = createMockComicSeries({
      _syncPending: true,
      id: -1,
      title: "Offline Comic",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText("En attente de synchronisation")).toBeInTheDocument();
  });

  it("does not show new release badge for finished series", () => {
    const recentDate = new Date();
    recentDate.setDate(recentDate.getDate() - 2);

    const comic = createMockComicSeries({
      latestPublishedIssue: 10,
      latestPublishedIssueUpdatedAt: recentDate.toISOString(),
      maxTomeNumber: 1,
      status: ComicStatus.FINISHED,
      title: "Complete",
      tomesCount: 1,
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByText("Nouveau")).not.toBeInTheDocument();
  });
});
