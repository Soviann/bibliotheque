import { fireEvent, screen, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import PurgeTool from "../../../pages/PurgeTool";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const API_BASE = "/api";

describe("PurgeTool", () => {
  it("renders the page title and days input", () => {
    renderWithProviders(<PurgeTool />);

    expect(
      screen.getByRole("heading", { name: "Purge de la corbeille" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("spinbutton")).toBeInTheDocument();
  });

  it("loads preview on mount with default days", async () => {
    server.use(
      http.get(`${API_BASE}/tools/purge/preview`, () =>
        HttpResponse.json([
          { deletedAt: "2025-01-01T00:00:00+00:00", id: 1, title: "Naruto" },
          { deletedAt: "2025-01-15T00:00:00+00:00", id: 2, title: "One Piece" },
        ]),
      ),
    );

    renderWithProviders(<PurgeTool />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("shows empty state when no series to purge", async () => {
    server.use(
      http.get(`${API_BASE}/tools/purge/preview`, () => HttpResponse.json([])),
    );

    renderWithProviders(<PurgeTool />);

    await waitFor(() => {
      expect(screen.getByText("Aucune série à purger")).toBeInTheDocument();
    });
  });

  it("opens confirm modal when clicking purge button", async () => {
    server.use(
      http.get(`${API_BASE}/tools/purge/preview`, () =>
        HttpResponse.json([
          { deletedAt: "2025-01-01T00:00:00+00:00", id: 1, title: "Naruto" },
        ]),
      ),
    );

    renderWithProviders(<PurgeTool />);

    await waitFor(() => {
      expect(screen.getByText("Naruto")).toBeInTheDocument();
    });

    const purgeButton = screen.getByRole("button", { name: /purger/i });
    fireEvent.click(purgeButton);

    await waitFor(() => {
      expect(screen.getByRole("dialog")).toBeInTheDocument();
    });
  });

  it("allows changing the days parameter", async () => {
    let requestedDays = "";
    server.use(
      http.get(`${API_BASE}/tools/purge/preview`, ({ request }) => {
        const url = new URL(request.url);
        requestedDays = url.searchParams.get("days") ?? "";
        return HttpResponse.json([]);
      }),
    );

    renderWithProviders(<PurgeTool />);

    // Wait for initial load
    await waitFor(() => {
      expect(requestedDays).toBe("30");
    });

    // Change days
    const input = screen.getByRole("spinbutton");
    fireEvent.change(input, { target: { value: "60" } });

    await waitFor(() => {
      expect(requestedDays).toBe("60");
    });
  });
});
