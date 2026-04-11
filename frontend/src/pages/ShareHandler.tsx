import { Loader2 } from "lucide-react";
import { useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { endpoints } from "../endpoints";
import { apiFetch } from "../services/api";
import type { ShareResponse } from "../types/api";

export default function ShareHandler() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const sharedUrl = searchParams.get("url") ?? searchParams.get("text") ?? "";
    const title = searchParams.get("title") ?? undefined;
    const text = searchParams.get("text") ?? undefined;

    if (!sharedUrl) {
      toast.error("Aucun lien à analyser.");
      void navigate("/", { replace: true });
      return;
    }

    apiFetch<ShareResponse>(endpoints.share, {
      body: JSON.stringify({ url: sharedUrl, title, text }),
      method: "POST",
    })
      .then((data) => {
        if (data.matched) {
          void navigate(`/comic/${data.seriesId}`, { replace: true });
        } else {
          void navigate("/comic/new", {
            replace: true,
            state: { lookupResult: data.lookupResult },
          });
        }
      })
      .catch(() => {
        toast.error("Impossible d'analyser le lien partagé.");
        void navigate("/", { replace: true });
      });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4">
      <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      <p className="text-text-secondary">Analyse du lien partagé…</p>
    </div>
  );
}
