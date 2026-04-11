import { useCallback, useState } from "react";

const colorCache = new Map<string, string>();
const DEFAULT_GLOW = "99, 102, 241"; // indigo fallback

// Luminosité relative (rec. 709) — clampe pour garantir le contraste en light/dark
const MIN_LUMINANCE = 0.15; // plancher : pas trop sombre (dark mode)
const MAX_LUMINANCE = 0.65; // plafond : pas trop clair (texte blanc lisible)

function clampLuminance(
  r: number,
  g: number,
  b: number,
): [number, number, number] {
  const luminance =
    0.2126 * (r / 255) + 0.7152 * (g / 255) + 0.0722 * (b / 255);
  if (luminance >= MIN_LUMINANCE && luminance <= MAX_LUMINANCE)
    return [r, g, b];
  const target = luminance < MIN_LUMINANCE ? MIN_LUMINANCE : MAX_LUMINANCE;
  const scale = target / Math.max(luminance, 0.001);
  return [
    Math.min(255, Math.round(r * scale)),
    Math.min(255, Math.round(g * scale)),
    Math.min(255, Math.round(b * scale)),
  ];
}

/**
 * Extrait la couleur dominante d'une image via canvas.
 * Retourne [couleur RGB, callback onLoad à brancher sur l'img existante].
 * Le callback exploite l'image déjà chargée par le navigateur — aucune requête supplémentaire.
 */
export function useDominantColor(
  src: string | null | undefined,
): [string, (img: HTMLImageElement) => void] {
  const [color, setColor] = useState(
    () => (src && colorCache.get(src)) ?? DEFAULT_GLOW,
  );

  const extractColor = useCallback(
    (img: HTMLImageElement) => {
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
        let r = 0,
          g = 0,
          b = 0,
          count = 0;

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
          const [cr, cg, cb] = clampLuminance(
            Math.round(r / count),
            Math.round(g / count),
            Math.round(b / count),
          );
          const result = `${cr}, ${cg}, ${cb}`;
          colorCache.set(src, result);
          setColor(result);
        }
      } catch {
        // CORS ou autre erreur — on garde le fallback
      }
    },
    [src],
  );

  return [color, extractColor];
}
