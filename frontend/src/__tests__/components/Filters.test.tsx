import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import Filters from "../../components/Filters";

describe("Filters", () => {
  it("renders type and status selects", () => {
    render(
      <Filters
        onStatusChange={vi.fn()}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    expect(
      screen.getByDisplayValue("Tous les types"),
    ).toBeInTheDocument();
    expect(
      screen.getByDisplayValue("Tous les statuts"),
    ).toBeInTheDocument();
  });

  it("lists all comic types", () => {
    render(
      <Filters
        onStatusChange={vi.fn()}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    expect(screen.getByText("Manga")).toBeInTheDocument();
    expect(screen.getByText("BD")).toBeInTheDocument();
    expect(screen.getByText("Comics")).toBeInTheDocument();
    expect(screen.getByText("Roman")).toBeInTheDocument();
    expect(screen.getByText("Webtoon")).toBeInTheDocument();
  });

  it("lists all comic statuses", () => {
    render(
      <Filters
        onStatusChange={vi.fn()}
        onTypeChange={vi.fn()}
        status=""
        type=""
      />,
    );

    expect(screen.getByText("En cours d'achat")).toBeInTheDocument();
    expect(screen.getByText("Complet")).toBeInTheDocument();
    expect(screen.getByText("Abandonné")).toBeInTheDocument();
    expect(screen.getByText("En pause")).toBeInTheDocument();
    expect(screen.getByText("Liste de souhaits")).toBeInTheDocument();
  });

  it("calls onTypeChange when type select changes", async () => {
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

    await user.selectOptions(
      screen.getByDisplayValue("Tous les types"),
      "manga",
    );
    expect(onTypeChange).toHaveBeenCalledWith("manga");
  });

  it("calls onStatusChange when status select changes", async () => {
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

    await user.selectOptions(
      screen.getByDisplayValue("Tous les statuts"),
      "complete",
    );
    expect(onStatusChange).toHaveBeenCalledWith("complete");
  });
});
