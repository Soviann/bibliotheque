import { screen } from "@testing-library/react";
import Login from "../../../pages/Login";
import { renderWithProviders } from "../../helpers/test-utils";

// Mock @react-oauth/google since it needs a provider and external scripts
vi.mock("@react-oauth/google", () => ({
  GoogleLogin: ({ onError }: { onError: () => void; onSuccess: (r: { credential: string }) => void }) => (
    <button onClick={() => onError()} type="button">
      Se connecter avec Google
    </button>
  ),
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
});
