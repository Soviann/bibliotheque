import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ErrorFallback from "../../../components/ErrorFallback";
import { renderWithProviders } from "../../helpers/test-utils";

describe("ErrorFallback", () => {
  it("renders error message from Error instance", () => {
    const error = new Error("Quelque chose a mal tourné");
    const resetErrorBoundary = vi.fn();

    renderWithProviders(
      <ErrorFallback error={error} resetErrorBoundary={resetErrorBoundary} />,
    );

    expect(screen.getByText("Une erreur est survenue")).toBeInTheDocument();
    expect(screen.getByText("Quelque chose a mal tourné")).toBeInTheDocument();
  });

  it("renders string error directly", () => {
    const resetErrorBoundary = vi.fn();

    renderWithProviders(
      <ErrorFallback error={"string error"} resetErrorBoundary={resetErrorBoundary} />,
    );

    expect(screen.getByText("string error")).toBeInTheDocument();
  });

  it("renders fallback message for null/undefined values", () => {
    const resetErrorBoundary = vi.fn();

    renderWithProviders(
      <ErrorFallback error={null} resetErrorBoundary={resetErrorBoundary} />,
    );

    expect(screen.getByText("Erreur inconnue")).toBeInTheDocument();
  });

  it("calls resetErrorBoundary when retry button is clicked", async () => {
    const user = userEvent.setup();
    const error = new Error("Test error");
    const resetErrorBoundary = vi.fn();

    renderWithProviders(
      <ErrorFallback error={error} resetErrorBoundary={resetErrorBoundary} />,
    );

    await user.click(screen.getByText("Réessayer"));

    expect(resetErrorBoundary).toHaveBeenCalledOnce();
  });
});
