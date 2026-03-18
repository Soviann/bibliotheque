import { describe, expect, it } from "vitest";
import {
  formCheckboxClassName,
  formCheckboxFocusClassName,
  formFocusRing,
  formInputClassName,
  formInputCompactClassName,
  formInputFocusClassName,
  formInputSecondaryClassName,
  formInputSecondaryFocusClassName,
  formLabelClassName,
  formListboxButtonClassName,
  formListboxButtonSecondaryClassName,
  formSelectClassName,
} from "../../../styles/formStyles";

describe("formStyles", () => {
  it("exports base input className", () => {
    expect(formInputClassName).toBe(
      "rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary",
    );
  });

  it("exports input with focus ring", () => {
    expect(formInputFocusClassName).toContain(formInputClassName);
    expect(formInputFocusClassName).toContain(formFocusRing);
  });

  it("exports secondary input variants with bg-surface-secondary", () => {
    expect(formInputSecondaryClassName).toContain("bg-surface-secondary");
    expect(formInputSecondaryClassName).not.toContain("bg-surface-primary");
    expect(formInputSecondaryFocusClassName).toContain(formFocusRing);
  });

  it("exports compact input with px-2", () => {
    expect(formInputCompactClassName).toContain("px-2");
    expect(formInputCompactClassName).not.toContain("px-3");
  });

  it("exports listbox button className with hover and focus", () => {
    expect(formListboxButtonClassName).toContain("hover:border-primary-400");
    expect(formListboxButtonClassName).toContain("bg-surface-primary");
  });

  it("exports secondary listbox button className", () => {
    expect(formListboxButtonSecondaryClassName).toContain("bg-surface-secondary");
    expect(formListboxButtonSecondaryClassName).not.toContain("bg-surface-primary");
  });

  it("exports select className with larger padding", () => {
    expect(formSelectClassName).toContain("py-2.5");
    expect(formSelectClassName).toContain(formFocusRing);
  });

  it("exports checkbox className", () => {
    expect(formCheckboxClassName).toBe(
      "h-4 w-4 rounded border-surface-border text-primary-600",
    );
  });

  it("exports checkbox with focus ring", () => {
    expect(formCheckboxFocusClassName).toContain(formCheckboxClassName);
    expect(formCheckboxFocusClassName).toContain("focus:ring-primary-500");
  });

  it("exports label className", () => {
    expect(formLabelClassName).toBe(
      "mb-1 block text-sm font-medium text-text-secondary",
    );
  });
});
