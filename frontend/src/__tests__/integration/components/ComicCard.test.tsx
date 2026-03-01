import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ComicCard from "../../../components/ComicCard";
import { createMockComicSeries, createMockTome } from "../../helpers/factories";
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

  it("renders cover image when coverUrl is available", () => {
    const comic = createMockComicSeries({
      coverUrl: "https://example.com/cover.jpg",
      title: "One Piece",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("One Piece");
    expect(img).toHaveAttribute("src", "https://example.com/cover.jpg");
  });

  it("renders cover from coverImage when coverUrl is null", () => {
    const comic = createMockComicSeries({
      coverImage: "naruto.jpg",
      coverUrl: null,
      title: "Naruto",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("Naruto");
    expect(img).toHaveAttribute("src", "/uploads/covers/naruto.jpg");
  });

  it("shows placeholder when no cover", () => {
    const comic = createMockComicSeries({
      coverImage: null,
      coverUrl: null,
      title: "No Cover",
    });

    renderWithProviders(<ComicCard comic={comic} />);

    const img = screen.getByAltText("No Cover");
    expect(img).toHaveAttribute("src", "/placeholder-cover.png");
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
      tomes: [createMockTome(), createMockTome(), createMockTome()],
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.getByText(/3 t\./)).toBeInTheDocument();
  });

  it("does not display tome count for oneshot", () => {
    const comic = createMockComicSeries({
      isOneShot: true,
      title: "Akira",
      tomes: [],
    });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByText(/t\./)).not.toBeInTheDocument();
  });

  it("shows delete button when onDelete is provided", () => {
    const comic = createMockComicSeries({ title: "Test" });
    const onDelete = vi.fn();

    renderWithProviders(<ComicCard comic={comic} onDelete={onDelete} />);

    expect(screen.getByTitle("Supprimer")).toBeInTheDocument();
  });

  it("does not show delete button when onDelete is not provided", () => {
    const comic = createMockComicSeries({ title: "Test" });

    renderWithProviders(<ComicCard comic={comic} />);

    expect(screen.queryByTitle("Supprimer")).not.toBeInTheDocument();
  });

  it("calls onDelete when delete button is clicked", async () => {
    const user = userEvent.setup();
    const comic = createMockComicSeries({ title: "Test" });
    const onDelete = vi.fn();

    renderWithProviders(<ComicCard comic={comic} onDelete={onDelete} />);

    await user.click(screen.getByTitle("Supprimer"));

    expect(onDelete).toHaveBeenCalledWith(comic);
  });

  it("has an edit button that navigates to edit page", async () => {
    const user = userEvent.setup();
    const comic = createMockComicSeries({ id: 5, title: "Test" });

    const { Route, Routes } = await import("react-router-dom");

    renderWithProviders(
      <Routes>
        <Route element={<ComicCard comic={comic} />} path="/" />
        <Route element={<div>Edit Page</div>} path="/comic/5/edit" />
      </Routes>,
    );

    const editButton = screen.getByTitle("Modifier");
    expect(editButton).toBeInTheDocument();

    await user.click(editButton);

    expect(screen.getByText("Edit Page")).toBeInTheDocument();
  });
});
