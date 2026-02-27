import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import ErrorFallback from "../../components/ErrorFallback";

describe("ErrorFallback", () => {
  it("renders error message from Error instance", () => {
    render(
      <ErrorFallback
        error={new Error("Something broke")}
        resetErrorBoundary={vi.fn()}
      />,
    );

    expect(screen.getByText("Something broke")).toBeInTheDocument();
    expect(
      screen.getByText("Une erreur est survenue"),
    ).toBeInTheDocument();
  });

  it("renders 'Erreur inconnue' for non-Error objects", () => {
    render(
      <ErrorFallback error="string error" resetErrorBoundary={vi.fn()} />,
    );

    expect(screen.getByText("Erreur inconnue")).toBeInTheDocument();
  });

  it("calls resetErrorBoundary on retry button click", async () => {
    const reset = vi.fn();
    const user = userEvent.setup();

    render(
      <ErrorFallback error={new Error("fail")} resetErrorBoundary={reset} />,
    );

    await user.click(screen.getByRole("button", { name: "Réessayer" }));
    expect(reset).toHaveBeenCalledOnce();
  });
});
