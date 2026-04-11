import { render, screen } from "@testing-library/react";
import SyncPendingIndicator from "../../../components/SyncPendingIndicator";

describe("SyncPendingIndicator", () => {
  it("renders with tooltip", () => {
    render(<SyncPendingIndicator />);
    expect(
      screen.getByTitle("En attente de synchronisation"),
    ).toBeInTheDocument();
  });

  it("applies custom className", () => {
    render(<SyncPendingIndicator className="mr-2" />);
    const el = screen.getByTitle("En attente de synchronisation");
    expect(el.className).toContain("mr-2");
  });
});
