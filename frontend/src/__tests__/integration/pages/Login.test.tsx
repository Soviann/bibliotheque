import { screen, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import Login from "../../../pages/Login";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

// Store the onSuccess/onError callbacks so tests can trigger them
let mockOnSuccess: ((r: { credential: string }) => void) | null = null;
let mockOnError: (() => void) | null = null;

// Mock @react-oauth/google since it needs a provider and external scripts
vi.mock("@react-oauth/google", () => ({
  GoogleLogin: ({ onError, onSuccess }: { onError: () => void; onSuccess: (r: { credential: string }) => void }) => {
    mockOnSuccess = onSuccess;
    mockOnError = onError;
    return (
      <button onClick={() => onError()} type="button">
        Se connecter avec Google
      </button>
    );
  },
  GoogleOAuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

describe("Login", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders the app title", () => {
    renderWithProviders(<Login />);

    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("renders the app icon", () => {
    renderWithProviders(<Login />);

    // The icon has alt="" (presentational), so use querySelector
    const img = document.querySelector('img[src="/app-icon.png"]');
    expect(img).toBeInTheDocument();
  });

  it("renders Google login button", () => {
    renderWithProviders(<Login />);

    expect(screen.getByText("Se connecter avec Google")).toBeInTheDocument();
  });

  it("shows error message when Google login fails", async () => {
    const { default: userEvent } = await import("@testing-library/user-event");
    const user = userEvent.setup();

    renderWithProviders(<Login />);

    await user.click(screen.getByText("Se connecter avec Google"));

    expect(screen.getByText("Erreur lors de la connexion Google. Veuillez réessayer.")).toBeInTheDocument();
  });

  it("shows API error when Google credential is valid but API rejects", async () => {
    const { default: userEvent } = await import("@testing-library/user-event");

    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json({ error: "Email non autorisé" }, { status: 403 }),
      ),
    );

    renderWithProviders(<Login />);

    // Trigger onSuccess with a valid credential
    const { act } = await import("@testing-library/react");
    await act(async () => {
      mockOnSuccess?.({ credential: "valid-google-token" });
    });

    await waitFor(() => {
      expect(screen.getByText("Email non autorisé")).toBeInTheDocument();
    });
  });

  it("does not call login when credential is undefined", async () => {
    let loginCalled = false;

    server.use(
      http.post("/api/login/google", () => {
        loginCalled = true;
        return HttpResponse.json({ token: "jwt" });
      }),
    );

    renderWithProviders(<Login />);

    const { act } = await import("@testing-library/react");
    await act(async () => {
      // Trigger onSuccess with undefined credential
      mockOnSuccess?.({ credential: undefined } as unknown as { credential: string });
    });

    // Give a tick for any potential async call
    await new Promise((r) => setTimeout(r, 50));
    expect(loginCalled).toBe(false);
  });

  it("shows loading state while login is pending", async () => {
    // Use a handler that delays response to keep loginPending=true
    server.use(
      http.post("/api/login/google", async () => {
        await new Promise((resolve) => setTimeout(resolve, 500));
        return HttpResponse.json({ token: "jwt" });
      }),
    );

    renderWithProviders(<Login />);

    const { act } = await import("@testing-library/react");
    // Trigger onSuccess but don't await the full mutation
    act(() => {
      mockOnSuccess?.({ credential: "valid-google-token" });
    });

    // The mutation is now in-flight, so isPending should be true
    await waitFor(() => {
      expect(screen.getByText("Connexion…")).toBeInTheDocument();
    });
  });
});
