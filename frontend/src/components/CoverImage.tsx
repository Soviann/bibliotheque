import { useState } from "react";

interface CoverImageProps {
  alt: string;
  className?: string;
  fallbackSrc?: string;
  height?: number;
  loading?: "eager" | "lazy";
  objectFit?: "contain" | "cover";
  onClick?: () => void;
  onImageLoad?: (img: HTMLImageElement) => void;
  src: string;
  viewTransitionName?: string;
  width?: number;
}

export default function CoverImage({
  alt,
  className = "",
  fallbackSrc,
  height,
  loading = "lazy",
  objectFit = "cover",
  onClick,
  onImageLoad,
  src,
  viewTransitionName,
  width,
}: CoverImageProps) {
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState(false);

  return (
    <div className={`relative overflow-hidden ${className}`} style={viewTransitionName ? { viewTransitionName } : undefined}>
      {!loaded && !error && (
        <div
          className="absolute inset-0 animate-pulse bg-surface-tertiary"
          data-testid="cover-skeleton"
        />
      )}
      <img
        alt={alt}
        className={`h-full w-full ${objectFit === "contain" ? "object-contain" : "object-cover"} transition-opacity duration-300 ${loaded ? "opacity-100" : "opacity-0"}`}
        crossOrigin="anonymous"
        height={height}
        loading={loading}
        onClick={onClick}
        onError={() => {
          setError(true);
          if (fallbackSrc) {
            setLoaded(true);
          }
        }}
        onLoad={(e) => {
          setLoaded(true);
          onImageLoad?.(e.currentTarget);
        }}
        src={error && fallbackSrc ? fallbackSrc : src}
        width={width}
      />
    </div>
  );
}
