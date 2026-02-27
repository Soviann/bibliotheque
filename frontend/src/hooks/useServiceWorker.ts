import { useEffect } from "react";
import { toast } from "sonner";

export function useServiceWorker() {
  useEffect(() => {
    async function register() {
      const { registerSW } = await import("virtual:pwa-register");
      registerSW({
        onNeedRefresh() {
          toast("Nouvelle version disponible", {
            action: {
              label: "Recharger",
              onClick: () => window.location.reload(),
            },
            duration: Infinity,
          });
        },
      });
    }
    register();
  }, []);
}
