import { render, screen } from "@testing-library/react";
import ComicCardSkeleton from "../../../components/ComicCardSkeleton";

describe("ComicCardSkeleton", () => {
  it("renders skeleton card with correct structure", () => {
    render(<ComicCardSkeleton />);

    expect(screen.getByTestId("comic-card-skeleton")).toBeInTheDocument();
    // Contains multiple skeleton boxes (cover + text lines + actions)
    expect(screen.getAllByTestId("skeleton-box").length).toBeGreaterThanOrEqual(4);
  });

  it("has the same border styling as ComicCard", () => {
    render(<ComicCardSkeleton />);

    const card = screen.getByTestId("comic-card-skeleton");
    expect(card).toHaveClass("rounded-xl");
    expect(card).toHaveClass("border");
    expect(card).toHaveClass("border-surface-border");
  });
});
