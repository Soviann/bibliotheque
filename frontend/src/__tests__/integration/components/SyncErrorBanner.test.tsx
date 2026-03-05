import "fake-indexeddb/auto";
import { render, screen, waitFor } from "@testing-library/react";
import { QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import SyncErrorBanner from "../../../components/SyncErrorBanner";
import { _resetDb, addSyncFailure } from "../../../services/offlineQueue";
import { createTestQueryClient } from "../../helpers/test-utils";

function renderBanner() {
  const queryClient = createTestQueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <SyncErrorBanner />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("SyncErrorBanner", () => {
  beforeEach(async () => {
    await _resetDb();
    await new Promise<void>((resolve, reject) => {
      const req = indexedDB.deleteDatabase("bibliotheque-offline");
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  });

  it("renders nothing when no failures", async () => {
    const { container } = renderBanner();

    // Attendre que la requête initiale se fasse
    await waitFor(() => {
      expect(container.innerHTML).toBe("");
    });
  });

  it("renders failures when present", async () => {
    await addSyncFailure({
      error: "Titre requis",
      httpStatus: 422,
      operation: "create",
      payload: { title: "" },
      resourceType: "comic_series",
    });

    renderBanner();

    await waitFor(() => {
      expect(screen.getByText(/Erreurs de synchronisation/)).toBeInTheDocument();
      expect(screen.getByText(/Création série — Titre requis/)).toBeInTheDocument();
    });
  });
});
