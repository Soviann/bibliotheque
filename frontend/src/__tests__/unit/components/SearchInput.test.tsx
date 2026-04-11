import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import SearchInput from "../../../components/SearchInput";

describe("SearchInput", () => {
  it("does not show clear button when value is empty", () => {
    render(<SearchInput onChange={vi.fn()} value="" />);
    expect(
      screen.queryByLabelText("Vider la recherche"),
    ).not.toBeInTheDocument();
  });

  it("shows clear button when value is non-empty", () => {
    render(<SearchInput onChange={vi.fn()} value="naruto" />);
    expect(screen.getByLabelText("Vider la recherche")).toBeInTheDocument();
  });

  it("calls onChange with empty string when clear button is clicked", async () => {
    const onChange = vi.fn();
    render(<SearchInput onChange={onChange} value="naruto" />);

    await userEvent.click(screen.getByLabelText("Vider la recherche"));

    expect(onChange).toHaveBeenCalledWith("");
  });

  it("auto-focuses input when autoFocus is true", () => {
    render(<SearchInput autoFocus onChange={vi.fn()} value="" />);
    expect(screen.getByRole("searchbox")).toHaveFocus();
  });

  it("does not auto-focus input by default", () => {
    render(<SearchInput onChange={vi.fn()} value="" />);
    expect(screen.getByRole("searchbox")).not.toHaveFocus();
  });
});
