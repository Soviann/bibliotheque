import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it } from "vitest";
import ContinueReading from "../../../components/ContinueReading";
import type { ComicSeries } from "../../../types/api";
import { createMockComicSeries } from "../../helpers/factories";

function renderComponent(comics: ComicSeries[]) {
  return render(
    <MemoryRouter>
      <ContinueReading comics={comics} />
    </MemoryRouter>,
  );
}

function expandSection() {
  fireEvent.click(screen.getByRole("button", { name: /Continuer la lecture/ }));
}

describe("ContinueReading", () => {
  it("renders section heading", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "One Piece",
        readCount: 2,
        boughtCount: 5,
      }),
    ];
    renderComponent(comics);
    expect(screen.getByText("Continuer la lecture")).toBeInTheDocument();
  });

  it("shows series where readCount < boughtCount", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "One Piece",
        readCount: 2,
        boughtCount: 5,
        onNasCount: 0,
      }),
    ];
    renderComponent(comics);
    expandSection();
    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("shows series where readCount < onNasCount", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "Naruto",
        readCount: 3,
        boughtCount: 0,
        onNasCount: 10,
      }),
    ];
    renderComponent(comics);
    expandSection();
    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("filters out series where readCount >= max(boughtCount, onNasCount)", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "All Read",
        readCount: 5,
        boughtCount: 5,
        onNasCount: 3,
      }),
      createMockComicSeries({
        id: 2,
        title: "Unread",
        readCount: 2,
        boughtCount: 5,
        onNasCount: 0,
      }),
    ];
    renderComponent(comics);
    expandSection();
    expect(screen.queryByText("All Read")).not.toBeInTheDocument();
    expect(screen.getByText("Unread")).toBeInTheDocument();
  });

  it("displays next tome number to read", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "One Piece",
        readCount: 7,
        boughtCount: 10,
        onNasCount: 0,
      }),
    ];
    renderComponent(comics);
    expandSection();
    expect(screen.getByText("Tome 8")).toBeInTheDocument();
  });

  it("renders nothing when no series match", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "Done",
        readCount: 5,
        boughtCount: 5,
        onNasCount: 5,
      }),
    ];
    const { container } = renderComponent(comics);
    expect(container.firstChild).toBeNull();
  });

  it("renders nothing with empty array", () => {
    const { container } = renderComponent([]);
    expect(container.firstChild).toBeNull();
  });

  it("links to comic detail page", () => {
    const comics = [
      createMockComicSeries({
        id: 42,
        title: "Bleach",
        readCount: 1,
        boughtCount: 5,
        onNasCount: 0,
      }),
    ];
    renderComponent(comics);
    expandSection();
    const link = screen.getByRole("link", { name: /Bleach/i });
    expect(link).toHaveAttribute("href", "/comic/42");
  });

  it("filters out one-shots", () => {
    const comics = [
      createMockComicSeries({
        id: 1,
        title: "One Shot",
        readCount: 0,
        boughtCount: 1,
        onNasCount: 0,
        isOneShot: true,
      }),
      createMockComicSeries({
        id: 2,
        title: "Series",
        readCount: 0,
        boughtCount: 3,
        onNasCount: 0,
      }),
    ];
    renderComponent(comics);
    expandSection();
    expect(screen.queryByText("One Shot")).not.toBeInTheDocument();
    expect(screen.getByText("Series")).toBeInTheDocument();
  });
});
