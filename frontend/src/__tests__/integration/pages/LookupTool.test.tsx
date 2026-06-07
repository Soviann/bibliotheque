import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import LookupTool from "../../../pages/LookupTool";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

describe("LookupTool", () => {
  it("renders the page title", () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 0 }),
      ),
    );

    renderWithProviders(<LookupTool />);

    expect(
      screen.getByRole("heading", { name: "Lookup métadonnées" }),
    ).toBeInTheDocument();
  });

  it("displays the preview count", async () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 15 }),
      ),
    );

    renderWithProviders(<LookupTool />);

    await waitFor(() => {
      expect(screen.getByText("15")).toBeInTheDocument();
    });
  });

  it("disables start button when count is 0", async () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 0 }),
      ),
    );

    renderWithProviders(<LookupTool />);

    await waitFor(() => {
      expect(screen.getByText("0")).toBeInTheDocument();
    });

    const startButton = screen.getByRole("button", { name: /lancer/i });
    expect(startButton).toBeDisabled();
  });

  it("shows type selector and limit input", () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 0 }),
      ),
    );

    renderWithProviders(<LookupTool />);

    expect(screen.getByLabelText("Type")).toBeInTheDocument();
    expect(screen.getByLabelText("Limite")).toBeInTheDocument();
  });

  it("shows force checkbox", () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 0 }),
      ),
    );

    renderWithProviders(<LookupTool />);

    expect(screen.getByLabelText(/forcer/i)).toBeInTheDocument();
  });

  it("queues enrichment via the run endpoint on click", async () => {
    let runCalled = false;
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 3 }),
      ),
      http.post("/api/tools/batch-lookup/run", () => {
        runCalled = true;
        return HttpResponse.json({ queued: 3 });
      }),
    );

    const user = userEvent.setup();
    renderWithProviders(<LookupTool />);

    await waitFor(() => {
      expect(screen.getByText("3")).toBeInTheDocument();
    });

    await user.click(screen.getByRole("button", { name: /lancer/i }));

    await waitFor(() => {
      expect(runCalled).toBe(true);
    });
  });
});
