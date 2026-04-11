import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import SelectListbox from "../../../components/SelectListbox";

const options = [
  { label: "Option A", value: "a" },
  { label: "Option B", value: "b" },
  { label: "Option C", value: "c" },
];

describe("SelectListbox", () => {
  it("renders selected option label", () => {
    render(<SelectListbox onChange={vi.fn()} options={options} value="b" />);
    expect(screen.getByText("Option B")).toBeInTheDocument();
  });

  it("renders placeholder when no value matches and placeholder is set", () => {
    render(
      <SelectListbox
        onChange={vi.fn()}
        options={options}
        placeholder="Choisir…"
        value=""
      />,
    );
    expect(screen.getByText("Choisir…")).toBeInTheDocument();
  });

  it("calls onChange when an option is selected", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<SelectListbox onChange={onChange} options={options} value="a" />);

    await user.click(screen.getByRole("button"));
    await user.click(screen.getByText("Option C"));

    expect(onChange).toHaveBeenCalledWith("c");
  });

  it("renders label when provided", () => {
    render(
      <SelectListbox
        label="Mon champ"
        onChange={vi.fn()}
        options={options}
        value="a"
      />,
    );
    expect(screen.getByText("Mon champ")).toBeInTheDocument();
  });

  it("adds aria-label on button when no label prop but placeholder provided", () => {
    render(
      <SelectListbox
        onChange={vi.fn()}
        options={options}
        placeholder="Choisir un type"
        value=""
      />,
    );
    expect(screen.getByRole("button")).toHaveAttribute(
      "aria-label",
      "Choisir un type",
    );
  });

  it("does not add aria-label on button when label prop is provided", () => {
    render(
      <SelectListbox
        label="Type"
        onChange={vi.fn()}
        options={options}
        value="a"
      />,
    );
    expect(screen.getByRole("button")).not.toHaveAttribute("aria-label");
  });

  it("falls back to first option when value doesn't match and no placeholder", () => {
    render(<SelectListbox onChange={vi.fn()} options={options} value="z" />);
    expect(screen.getByText("Option A")).toBeInTheDocument();
  });
});
