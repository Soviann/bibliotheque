import { describe, expect, it } from "vitest";
import { queryClient } from "../../queryClient";

describe("queryClient default options", () => {
  it("has correct query defaults", () => {
    const defaults = queryClient.getDefaultOptions().queries;

    expect(defaults?.gcTime).toBe(60 * 60 * 1000);
    expect(defaults?.networkMode).toBe("offlineFirst");
    expect(defaults?.refetchOnWindowFocus).toBe(false);
    expect(defaults?.retry).toBe(1);
    expect(defaults?.staleTime).toBe(30 * 60 * 1000);
  });

  it("has correct mutation defaults", () => {
    const defaults = queryClient.getDefaultOptions().mutations;

    expect(defaults?.networkMode).toBe("offlineFirst");
  });
});
