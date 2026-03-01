import { render, screen } from "@testing-library/react";
import SkeletonBox from "../../../components/SkeletonBox";

describe("SkeletonBox", () => {
  it("renders with animate-pulse class", () => {
    render(<SkeletonBox />);

    const box = screen.getByTestId("skeleton-box");
    expect(box).toHaveClass("animate-pulse");
    expect(box).toHaveClass("bg-surface-tertiary");
  });

  it("applies custom className", () => {
    render(<SkeletonBox className="h-4 w-3/4" />);

    const box = screen.getByTestId("skeleton-box");
    expect(box).toHaveClass("h-4");
    expect(box).toHaveClass("w-3/4");
  });
});
