import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, vi } from "vitest";
import SeriesMultiSelect from "../../../components/SeriesMultiSelect";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const comics = [
  createMockComicSeries({ id: 1, title: "Naruto", tomes: [] }),
  createMockComicSeries({ id: 2, title: "One Piece", tomes: [] }),
  createMockComicSeries({ id: 3, title: "Bleach", tomes: [] }),
];

function setupHandler() {
  server.use(
    http.get("/api/comic_series", () =>
      HttpResponse.json(createMockHydraCollection(comics)),
    ),
  );
}

describe("SeriesMultiSelect", () => {
  it("renders series list from API", async () => {
    setupHandler();

    renderWithProviders(
      <SeriesMultiSelect onSelectionChange={vi.fn()} selectedIds={[]} />,
    );

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Bleach")).toBeInTheDocument();
  });

  it("filters results by search (case-insensitive)", async () => {
    setupHandler();
    const user = userEvent.setup();

    renderWithProviders(
      <SeriesMultiSelect onSelectionChange={vi.fn()} selectedIds={[]} />,
    );

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Rechercher une série..."), "one");

    await waitFor(() => {
      expect(screen.getByText("One Piece")).toBeInTheDocument();
      expect(screen.queryByText("Naruto")).not.toBeInTheDocument();
      expect(screen.queryByText("Bleach")).not.toBeInTheDocument();
    });
  });

  it("calls onSelectionChange when toggling checkbox", async () => {
    setupHandler();
    const user = userEvent.setup();
    const onSelectionChange = vi.fn();

    renderWithProviders(
      <SeriesMultiSelect onSelectionChange={onSelectionChange} selectedIds={[]} />,
    );

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[0]);

    expect(onSelectionChange).toHaveBeenCalledWith([1]);
  });

  it("shows selected items as chips", async () => {
    setupHandler();

    renderWithProviders(
      <SeriesMultiSelect onSelectionChange={vi.fn()} selectedIds={[1, 3]} />,
    );

    await waitFor(() => {
      // Naruto appears twice: once as chip, once in list
      expect(screen.getAllByText("Naruto")).toHaveLength(2);
    });

    // Bleach also appears twice (chip + list)
    expect(screen.getAllByText("Bleach")).toHaveLength(2);

    // "2 sélectionnées" in the count text
    expect(screen.getByText(/2 sélectionnée/)).toBeInTheDocument();
  });

  it("calls onSelectionChange without removed ID when removing chip", async () => {
    setupHandler();
    const user = userEvent.setup();
    const onSelectionChange = vi.fn();

    renderWithProviders(
      <SeriesMultiSelect onSelectionChange={onSelectionChange} selectedIds={[1, 2]} />,
    );

    await waitFor(() => {
      expect(screen.getAllByText("Naruto")).toHaveLength(2);
    });

    // Click the remove button (X) on the first chip
    const removeButtons = screen.getAllByRole("button");
    await user.click(removeButtons[0]);

    expect(onSelectionChange).toHaveBeenCalledWith([2]);
  });
});
