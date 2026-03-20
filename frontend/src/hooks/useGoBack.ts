import { useCallback } from "react";
import { useNavigate } from "react-router-dom";

/**
 * Retourne une fonction « retour intelligent » :
 * - si l'utilisateur a un historique in-app (idx > 0), navigate(-1)
 * - sinon (favori, lien partagé, onglet neuf), redirige vers `fallback`
 */
export function useGoBack(fallback = "/"): () => void {
  const navigate = useNavigate();

  return useCallback(() => {
    const idx = (window.history.state as { idx?: number } | null)?.idx ?? 0;
    if (idx > 0) {
      navigate(-1);
    } else {
      navigate(fallback, { viewTransition: true });
    }
  }, [fallback, navigate]);
}
