import { Html5Qrcode } from "html5-qrcode";
import { Barcode, CheckCircle2, Loader2, PlusCircle } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { fetchLookupIsbn } from "../hooks/useLookup";
import type { LookupResult } from "../types/api";
import CoverImage from "./CoverImage";

interface QuickAddScanProps {
  batchMode: boolean;
  onAdd: (result: {
    coverUrl: string | null;
    title: string;
    tomeNumber: number;
  }) => void;
}

export default function QuickAddScan({ batchMode, onAdd }: QuickAddScanProps) {
  const [scanning, setScanning] = useState(false);
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState<LookupResult | null>(null);
  const scannerRef = useRef<Html5Qrcode | null>(null);
  const onAddRef = useRef(onAdd);
  onAddRef.current = onAdd;

  const startScanner = useCallback(() => {
    setPreview(null);
    setScanning(true);
  }, []);

  useEffect(() => {
    if (!scanning) return;

    const container = document.getElementById("quick-add-scanner");
    if (!container) return;

    const scanner = new Html5Qrcode("quick-add-scanner");
    scannerRef.current = scanner;

    scanner
      .start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { height: 100, width: 250 } },
        async (decodedText) => {
          const isbn = decodedText.replace(/[^0-9X]/gi, "");
          if (isbn.length !== 10 && isbn.length !== 13) return;

          scanner.stop().catch(() => {});
          scannerRef.current = null;
          setScanning(false);
          setLoading(true);

          try {
            const result = await fetchLookupIsbn(isbn);
            if (result.title) {
              setPreview(result);
            } else {
              toast.error("ISBN non trouvé");
              startScanner();
            }
          } catch {
            toast.error("Erreur lors de la recherche");
            startScanner();
          } finally {
            setLoading(false);
          }
        },
        () => {},
      )
      .catch(() => {
        scannerRef.current = null;
        setScanning(false);
        toast.error("Impossible d'accéder à la caméra");
      });

    return () => {
      if (scannerRef.current === scanner) {
        scanner.stop().catch(() => {});
        scannerRef.current = null;
      }
    };
  }, [scanning, startScanner]);

  const handleConfirm = useCallback(() => {
    if (!preview) return;
    onAddRef.current({
      coverUrl: preview.thumbnail,
      title: preview.title ?? "Sans titre",
      tomeNumber: preview.tomeNumber ?? 1,
    });
    setPreview(null);
    if (batchMode) {
      startScanner();
    }
  }, [preview, batchMode, startScanner]);

  if (loading) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-3">
        <Loader2 className="h-8 w-8 animate-spin text-amber-500" />
        <p className="text-sm text-text-muted">Recherche ISBN en cours…</p>
      </div>
    );
  }

  if (preview) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center px-4">
        {/* Live Detection Card */}
        <div className="w-full max-w-sm space-y-3.5 rounded-3xl border border-surface-border bg-surface-secondary/70 p-4 shadow-xl dark:border-white/10 dark:bg-surface-elevated/40">
          <div className="flex items-center justify-between text-xs">
            <span className="flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="h-3.5 w-3.5" />
              ISBN détecté{preview.isbn ? ` : ${preview.isbn}` : ""}
            </span>
            <span className="font-mono text-[10px] text-text-muted">
              {preview.publisher ?? "Bedetheque · BNF"}
            </span>
          </div>

          <div className="flex items-center gap-3">
            {preview.thumbnail ? (
              <CoverImage
                alt={preview.title ?? ""}
                className="h-20 w-14 shrink-0 rounded-xl object-cover shadow-md"
                src={preview.thumbnail}
              />
            ) : (
              <div className="flex h-20 w-14 shrink-0 items-center justify-center rounded-xl bg-surface-tertiary">
                <Barcode className="h-6 w-6 text-text-muted" />
              </div>
            )}
            <div className="min-w-0 flex-1">
              <h3 className="truncate font-serif text-base font-bold text-text-primary">
                {preview.title}
              </h3>
              <p className="text-xs font-bold text-amber-600 dark:text-amber-400">
                {preview.tomeNumber ? `Tome ${preview.tomeNumber}` : "Tome 1"}
              </p>
              {preview.authors && (
                <p className="truncate text-[11px] text-text-muted">
                  {preview.authors}
                </p>
              )}
            </div>
          </div>

          {/* 1-tap confirmation button */}
          <button
            className="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 py-3 text-sm font-bold text-neutral-950 shadow-xl shadow-amber-500/20 transition hover:bg-amber-400 active:scale-95"
            onClick={handleConfirm}
            type="button"
          >
            <PlusCircle className="h-4 w-4 stroke-[2.5]" />
            <span>Valider et Continuer (1 tap)</span>
          </button>

          <button
            className="w-full rounded-xl py-1.5 text-center text-xs text-text-muted transition hover:text-text-primary"
            onClick={startScanner}
            type="button"
          >
            Rescanner
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 px-4 py-2">
      {!scanning ? (
        <button
          aria-label="Appuyer pour scanner"
          className="group relative flex aspect-[4/3] w-full max-w-xs flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed border-amber-500/50 bg-neutral-900/90 text-white shadow-xl transition active:scale-95"
          onClick={startScanner}
          type="button"
        >
          {/* Reticle Corners */}
          <div className="absolute left-3 top-3 h-6 w-6 rounded-tl-lg border-l-2 border-t-2 border-amber-400" />
          <div className="absolute right-3 top-3 h-6 w-6 rounded-tr-lg border-r-2 border-t-2 border-amber-400" />
          <div className="absolute bottom-3 left-3 h-6 w-6 rounded-bl-lg border-b-2 border-l-2 border-amber-400" />
          <div className="absolute bottom-3 right-3 h-6 w-6 rounded-br-lg border-b-2 border-r-2 border-amber-400" />

          {/* Laser Line */}
          <div className="laser-line pointer-events-none absolute inset-x-4 top-4 h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent shadow-[0_0_12px_#f59e0b]" />

          <div className="z-10 space-y-2 px-4 text-center">
            <Barcode className="mx-auto h-12 w-12 text-amber-400/80 stroke-[1.5]" />
            <p className="text-xs font-bold text-white">
              Alignez le code-barres ISBN
            </p>
            <p className="text-[10px] text-neutral-300">
              Appuyer pour scanner
            </p>
          </div>
        </button>
      ) : (
        <div
          className="aspect-[4/3] w-full max-w-xs overflow-hidden rounded-3xl border-2 border-dashed border-amber-500/50 shadow-xl"
          id="quick-add-scanner"
        />
      )}
    </div>
  );
}
