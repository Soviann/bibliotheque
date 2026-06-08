import { describe, expect, it } from "vitest";
import type { LookupCandidate } from "../../../types/api";
import { candidateVolumeChip, candidateYear } from "../../../utils/lookupCandidate";

function makeCandidate(overrides: Partial<LookupCandidate> = {}): LookupCandidate {
  return {
    authors: null,
    description: null,
    isbn: null,
    isOneShot: null,
    latestPublishedIssue: null,
    publishedDate: null,
    publisher: null,
    thumbnail: null,
    title: null,
    tomeEnd: null,
    tomeNumber: null,
    ...overrides,
  };
}

describe("candidateYear", () => {
  it('returns the year string for a plain year "2000"', () => {
    expect(candidateYear("2000")).toBe("2000");
  });

  it('extracts the year from a full date "2000-05-12"', () => {
    expect(candidateYear("2000-05-12")).toBe("2000");
  });

  it('extracts the year from a natural-language date "mai 2014"', () => {
    expect(candidateYear("mai 2014")).toBe("2014");
  });

  it("returns null for null", () => {
    expect(candidateYear(null)).toBeNull();
  });

  it('returns null for an empty string ""', () => {
    expect(candidateYear("")).toBeNull();
  });

  it('returns null when no year is present ("sans année")', () => {
    expect(candidateYear("sans année")).toBeNull();
  });
});

describe("candidateVolumeChip", () => {
  it('returns {kind:"oneshot"} when isOneShot is true, even with tomeEnd set', () => {
    expect(candidateVolumeChip(makeCandidate({ isOneShot: true, tomeEnd: 6 }))).toEqual({
      kind: "oneshot",
    });
  });

  it('returns {kind:"count", label:"6 tomes"} when tomeEnd is 6', () => {
    expect(candidateVolumeChip(makeCandidate({ tomeEnd: 6 }))).toEqual({
      kind: "count",
      label: "6 tomes",
    });
  });

  it('returns singular "1 tome" when tomeEnd is 1', () => {
    expect(candidateVolumeChip(makeCandidate({ tomeEnd: 1 }))).toEqual({
      kind: "count",
      label: "1 tome",
    });
  });

  it("falls back to latestPublishedIssue when tomeEnd is null", () => {
    expect(
      candidateVolumeChip(makeCandidate({ tomeEnd: null, latestPublishedIssue: 12 })),
    ).toEqual({ kind: "count", label: "12 tomes" });
  });

  it("falls back to tomeNumber when tomeEnd and latestPublishedIssue are null", () => {
    expect(
      candidateVolumeChip(
        makeCandidate({ tomeEnd: null, latestPublishedIssue: null, tomeNumber: 3 }),
      ),
    ).toEqual({ kind: "count", label: "3 tomes" });
  });

  it("returns null when all volume fields are null and isOneShot is false", () => {
    expect(candidateVolumeChip(makeCandidate({ isOneShot: false }))).toBeNull();
  });

  it("returns null when tomeEnd is 0 and other volume fields are null", () => {
    expect(
      candidateVolumeChip(makeCandidate({ tomeEnd: 0, latestPublishedIssue: null, tomeNumber: null })),
    ).toBeNull();
  });
});
