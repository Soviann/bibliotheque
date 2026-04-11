import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import AddedStack from "../../../components/AddedStack";
import type { QuickAddItem } from "../../../hooks/useQuickAdd";

describe("AddedStack", () => {
  it("renders nothing when items is empty", () => {
    const { container } = render(<AddedStack items={[]} />);
    expect(container.firstChild).toBeNull();
  });

  it("renders count for multiple items", () => {
    const items: QuickAddItem[] = [
      { coverUrl: null, title: "One Piece", tomeNumber: 5 },
      { coverUrl: null, title: "Naruto", tomeNumber: 3 },
    ];
    render(<AddedStack items={items} />);
    expect(screen.getByText("2 tomes ajoutés")).toBeInTheDocument();
  });

  it("renders singular for one item", () => {
    const items: QuickAddItem[] = [
      { coverUrl: "/c.jpg", title: "Bleach", tomeNumber: 1 },
    ];
    render(<AddedStack items={items} />);
    expect(screen.getByText("1 tome ajouté")).toBeInTheDocument();
  });
});
