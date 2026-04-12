import { useEffect } from "react";
import { useLocation, useNavigationType } from "react-router-dom";
import type { StateSnapshot } from "react-virtuoso";

const STORAGE_PREFIX = "virtuoso:";

/**
 * Gère le scroll-to-top sur PUSH et désactive la restauration native du navigateur.
 * La restauration POP est déléguée à react-virtuoso via StateSnapshot.
 */
export function useScrollRestoration(): void {
  const { key } = useLocation();
  const navigationType = useNavigationType();

  useEffect(() => {
    window.history.scrollRestoration = "manual";
  }, []);

  // Scroll to top sur PUSH uniquement
  useEffect(() => {
    if (navigationType === "PUSH") {
      window.scrollTo(0, 0);
    }
  }, [key, navigationType]);
}

/** Sauvegarde un StateSnapshot Virtuoso dans sessionStorage. */
export function saveVirtuosoState(
  locationKey: string,
  snapshot: StateSnapshot,
): void {
  sessionStorage.setItem(STORAGE_PREFIX + locationKey, JSON.stringify(snapshot));
}

/** Lit un StateSnapshot sauvegardé pour une clé de location. */
export function getSavedVirtuosoState(
  locationKey: string,
): StateSnapshot | undefined {
  const raw = sessionStorage.getItem(STORAGE_PREFIX + locationKey);
  if (!raw) return undefined;
  try {
    return JSON.parse(raw) as StateSnapshot;
  } catch {
    return undefined;
  }
}
