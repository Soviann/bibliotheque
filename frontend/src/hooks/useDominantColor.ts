import { useCallback, useState } from "react";

const colorCache = new Map<string, string>();
const DEFAULT_GLOW = "99, 102, 241"; // indigo fallback

/**
 * Extrait la couleur dominante d'une image via canvas.
 * Retourne [couleur RGB, callback onLoad à brancher sur l'img existante].
 * Le callback exploite l'image déjà chargée par le navigateur — aucune requête supplémentaire.
 */
export function useDominantColor(src: string | null | undefined): [string, (img: HTMLImageElement) => void] {
  const [color, setColor] = useState(() => (src && colorCache.get(src)) ?? DEFAULT_GLOW);

  const extractColor = useCallback((img: HTMLImageElement) => {
    if (!src) return;

    const cached = colorCache.get(src);
    if (cached) {
      setColor(cached);
      return;
    }

    try {
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");
      if (!ctx) return;

      // Échantillonnage sur une petite image pour la performance
      canvas.width = 10;
      canvas.height = 10;
      ctx.drawImage(img, 0, 0, 10, 10);

      const data = ctx.getImageData(0, 0, 10, 10).data;
      let r = 0, g = 0, b = 0, count = 0;

      for (let i = 0; i < data.length; i += 4) {
        // Ignorer les pixels très sombres ou très clairs (arrière-plan)
        const brightness = data[i] + data[i + 1] + data[i + 2];
        if (brightness > 60 && brightness < 700) {
          r += data[i];
          g += data[i + 1];
          b += data[i + 2];
          count++;
        }
      }

      if (count > 0) {
        const result = `${Math.round(r / count)}, ${Math.round(g / count)}, ${Math.round(b / count)}`;
        colorCache.set(src, result);
        setColor(result);
      }
    } catch {
      // CORS ou autre erreur — on garde le fallback
    }
  }, [src]);

  return [color, extractColor];
}
