import { describe, expect, it } from "vitest";
import {
  formCheckboxClassName,
  formFocusRing,
  formInputClassName,
  formLabelClassName,
  formListboxButtonClassName,
  formSelectClassName,
} from "../../../styles/formStyles";

describe("formStyles", () => {
  it("exports base input className with expected tokens", () => {
    expect(formInputClassName).toContain("rounded-lg");
    expect(formInputClassName).toContain("border-surface-border");
    expect(formInputClassName).toContain("bg-surface-primary");
    expect(formInputClassName).toContain("text-sm");
    expect(formInputClassName).toContain("px-3 py-2");
  });

  it("exports focus ring className", () => {
    expect(formFocusRing).toContain("focus:border-primary-500");
    expect(formFocusRing).toContain("focus:ring-1");
  });

  it("exports listbox button className with hover and focus", () => {
    expect(formListboxButtonClassName).toContain("hover:border-primary-400");
    expect(formListboxButtonClassName).toContain("focus:ring-1");
  });

  it("exports select className with focus ring", () => {
    expect(formSelectClassName).toContain("rounded-lg");
    expect(formSelectClassName).toContain("focus:ring-1");
  });

  it("exports checkbox className", () => {
    expect(formCheckboxClassName).toContain("rounded");
    expect(formCheckboxClassName).toContain("text-primary-600");
  });

  it("exports label className", () => {
    expect(formLabelClassName).toContain("text-sm");
    expect(formLabelClassName).toContain("font-medium");
    expect(formLabelClassName).toContain("text-text-secondary");
  });
});
