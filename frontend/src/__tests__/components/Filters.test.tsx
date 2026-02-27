import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import Filters from "../../components/Filters";

describe("Filters", () => {
  it("renders type and status listboxes", () => {
    render(
      <Filters
        onStatusChange={vi.fn()}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    expect(screen.getByText("Tous les types")).toBeInTheDocument();
    expect(screen.getByText("Tous les statuts")).toBeInTheDocument();
  });

  it("hides status when hideStatus is true", () => {
    render(
      <Filters
        hideStatus
        onStatusChange={vi.fn()}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    expect(screen.getByText("Tous les types")).toBeInTheDocument();
    expect(screen.queryByText("Tous les statuts")).not.toBeInTheDocument();
  });

  it("calls onTypeChange when a type is selected", async () => {
    const onTypeChange = vi.fn();
    const user = userEvent.setup();

    render(
      <Filters
        onStatusChange={vi.fn()}
        onTypeChange={onTypeChange}
        status=""
        type=""
      />,
    );

    await user.click(screen.getByText("Tous les types"));
    await user.click(screen.getByText("Manga"));
    expect(onTypeChange).toHaveBeenCalledWith("manga");
  });

  it("calls onStatusChange when a status is selected", async () => {
    const onStatusChange = vi.fn();
    const user = userEvent.setup();

    render(
      <Filters
        onStatusChange={onStatusChange}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    await user.click(screen.getByText("Tous les statuts"));
    await user.click(screen.getByText("Complet"));
    expect(onStatusChange).toHaveBeenCalledWith("complete");
  });
});
