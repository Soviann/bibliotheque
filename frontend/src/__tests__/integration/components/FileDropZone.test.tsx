import { fireEvent, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import FileDropZone from "../../../components/FileDropZone";
import { renderWithProviders } from "../../helpers/test-utils";

describe("FileDropZone", () => {
  it("renders upload prompt", () => {
    renderWithProviders(<FileDropZone onFileSelect={vi.fn()} />);

    expect(screen.getByText(/glisser-deposer/i)).toBeInTheDocument();
  });

  it("calls onFileSelect when a file is selected via input", () => {
    const onFileSelect = vi.fn();
    renderWithProviders(<FileDropZone onFileSelect={onFileSelect} />);

    const file = new File(["test"], "test.xlsx", {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });

    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    expect(onFileSelect).toHaveBeenCalledWith(file);
  });

  it("shows file name after selection", () => {
    const onFileSelect = vi.fn();
    renderWithProviders(<FileDropZone onFileSelect={onFileSelect} />);

    const file = new File(["test"], "mon-fichier.xlsx", {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });

    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    expect(screen.getByText("mon-fichier.xlsx")).toBeInTheDocument();
  });
});
