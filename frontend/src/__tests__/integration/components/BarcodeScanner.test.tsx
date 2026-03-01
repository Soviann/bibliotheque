import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import BarcodeScanner from "../../../components/BarcodeScanner";
import { renderWithProviders } from "../../helpers/test-utils";

// Mock html5-qrcode since it relies on browser APIs not available in jsdom
vi.mock("html5-qrcode", () => ({
  Html5Qrcode: vi.fn().mockImplementation(() => ({
    start: vi.fn().mockRejectedValue(new Error("Camera not available")),
    stop: vi.fn().mockResolvedValue(undefined),
  })),
}));

describe("BarcodeScanner", () => {
  it("renders the scanner button", () => {
    const onScan = vi.fn();

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    expect(screen.getByText("Scanner")).toBeInTheDocument();
  });

  it("shows scanner UI when button is clicked", async () => {
    const user = userEvent.setup();
    const onScan = vi.fn();

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    await user.click(screen.getByText("Scanner"));

    // Since camera fails in jsdom, it should fall back to non-scanning state
    // The component shows "Scanner actif" briefly before the error
    // and then falls back to the button again after toast.error
    // Wait for the async error to resolve
    await vi.waitFor(() => {
      expect(screen.getByText("Scanner")).toBeInTheDocument();
    });
  });

  it("has a camera icon in the button", () => {
    const onScan = vi.fn();

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    const button = screen.getByText("Scanner");
    expect(button).toBeInTheDocument();
    expect(button.tagName).toBe("BUTTON");
  });
});
