import { useState } from "react";

interface CoverImageProps {
  alt: string;
  className?: string;
  fallbackSrc?: string;
  height?: number;
  loading?: "eager" | "lazy";
  onClick?: () => void;
  src: string;
  width?: number;
}

export default function CoverImage({
  alt,
  className = "",
  fallbackSrc,
  height,
  loading = "lazy",
  onClick,
  src,
  width,
}: CoverImageProps) {
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState(false);

  return (
    <div className={`relative overflow-hidden ${className}`}>
      {!loaded && !error && (
        <div
          className="absolute inset-0 animate-pulse bg-surface-tertiary"
          data-testid="cover-skeleton"
        />
      )}
      <img
        alt={alt}
        className={`h-full w-full object-cover transition-opacity duration-300 ${loaded ? "opacity-100" : "opacity-0"}`}
        height={height}
        loading={loading}
        onClick={onClick}
        onError={() => {
          setError(true);
          if (fallbackSrc) {
            setLoaded(true);
          }
        }}
        onLoad={() => setLoaded(true)}
        src={error && fallbackSrc ? fallbackSrc : src}
        width={width}
      />
    </div>
  );
}
