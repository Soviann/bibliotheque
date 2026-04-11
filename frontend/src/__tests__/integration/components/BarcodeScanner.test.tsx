import { act, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Html5Qrcode } from "html5-qrcode";
import { toast } from "sonner";
import BarcodeScanner from "../../../components/BarcodeScanner";
import { renderWithProviders } from "../../helpers/test-utils";

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      success: vi.fn(),
    }),
  };
});

let mockStart: ReturnType<typeof vi.fn>;
let mockStop: ReturnType<typeof vi.fn>;

vi.mock("html5-qrcode", () => ({
  Html5Qrcode: vi.fn(),
}));

beforeEach(() => {
  mockStop = vi.fn().mockResolvedValue(undefined);
  mockStart = vi.fn().mockResolvedValue(undefined);

  vi.mocked(Html5Qrcode).mockImplementation(function () {
    return { start: mockStart, stop: mockStop } as unknown as InstanceType<
      typeof Html5Qrcode
    >;
  });
});

describe("BarcodeScanner", () => {
  it("renders the scanner button", () => {
    renderWithProviders(<BarcodeScanner onScan={vi.fn()} />);

    expect(screen.getByText("Scanner")).toBeInTheDocument();
  });

  it("shows scanner UI when button is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(<BarcodeScanner onScan={vi.fn()} />);

    await user.click(screen.getByText("Scanner"));

    expect(screen.getByText("Scanner actif")).toBeInTheDocument();
    expect(screen.getByLabelText("Fermer le scanner")).toBeInTheDocument();
  });

  it("starts Html5Qrcode after clicking scanner button", async () => {
    const user = userEvent.setup();

    renderWithProviders(<BarcodeScanner onScan={vi.fn()} />);

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(Html5Qrcode).toHaveBeenCalledWith("barcode-scanner");
      expect(mockStart).toHaveBeenCalledWith(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 100 } },
        expect.any(Function),
        expect.any(Function),
      );
    });
  });

  it("stops scanner and returns to button when X is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(<BarcodeScanner onScan={vi.fn()} />);

    await user.click(screen.getByText("Scanner"));
    expect(screen.getByText("Scanner actif")).toBeInTheDocument();

    await user.click(screen.getByLabelText("Fermer le scanner"));

    expect(mockStop).toHaveBeenCalled();
    expect(screen.getByText("Scanner")).toBeInTheDocument();
  });

  it("calls onScan with cleaned ISBN-13 and stops scanner", async () => {
    const user = userEvent.setup();
    const onScan = vi.fn();
    let decodedTextCallback: ((text: string) => void) | null = null;

    mockStart.mockImplementation(
      (
        _camera: unknown,
        _config: unknown,
        onSuccess: (text: string) => void,
      ) => {
        decodedTextCallback = onSuccess;
        return Promise.resolve();
      },
    );

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(decodedTextCallback).not.toBeNull();
    });

    act(() => decodedTextCallback!("978-2-505-08067-3"));

    expect(onScan).toHaveBeenCalledWith("9782505080673");
    expect(mockStop).toHaveBeenCalled();
  });

  it("calls onScan with cleaned ISBN-10", async () => {
    const user = userEvent.setup();
    const onScan = vi.fn();
    let decodedTextCallback: ((text: string) => void) | null = null;

    mockStart.mockImplementation(
      (
        _camera: unknown,
        _config: unknown,
        onSuccess: (text: string) => void,
      ) => {
        decodedTextCallback = onSuccess;
        return Promise.resolve();
      },
    );

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(decodedTextCallback).not.toBeNull();
    });

    act(() => decodedTextCallback!("123456789X"));

    expect(onScan).toHaveBeenCalledWith("123456789X");
  });

  it("ignores barcodes that are not ISBN-10 or ISBN-13", async () => {
    const user = userEvent.setup();
    const onScan = vi.fn();
    let decodedTextCallback: ((text: string) => void) | null = null;

    mockStart.mockImplementation(
      (
        _camera: unknown,
        _config: unknown,
        onSuccess: (text: string) => void,
      ) => {
        decodedTextCallback = onSuccess;
        return Promise.resolve();
      },
    );

    renderWithProviders(<BarcodeScanner onScan={onScan} />);

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(decodedTextCallback).not.toBeNull();
    });

    act(() => decodedTextCallback!("123456789012"));

    expect(onScan).not.toHaveBeenCalled();
    expect(mockStop).not.toHaveBeenCalled();
  });

  it("shows toast and returns to button when camera access fails", async () => {
    const user = userEvent.setup();
    mockStart.mockRejectedValue(new Error("Camera not available"));

    renderWithProviders(<BarcodeScanner onScan={vi.fn()} />);

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        "Impossible d'accéder à la caméra",
      );
    });

    expect(screen.getByText("Scanner")).toBeInTheDocument();
  });

  it("stops scanner on unmount", async () => {
    const user = userEvent.setup();

    const { unmount } = renderWithProviders(
      <BarcodeScanner onScan={vi.fn()} />,
    );

    await user.click(screen.getByText("Scanner"));

    await waitFor(() => {
      expect(mockStart).toHaveBeenCalled();
    });

    unmount();

    expect(mockStop).toHaveBeenCalled();
  });
});
