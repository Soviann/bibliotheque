import { GoogleLogin } from "@react-oauth/google";
import { useState } from "react";
import { useAuth } from "../hooks/useAuth";

export default function Login() {
  const { login, loginError, loginPending } = useAuth();
  const [googleError, setGoogleError] = useState(false);

  return (
    <div className="flex min-h-screen items-center justify-center bg-surface-secondary px-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <img alt="" className="mx-auto h-20 w-20 rounded-2xl shadow-lg" src="/app-icon.png" />
          <h1 className="mt-4 font-display text-3xl font-bold text-text-primary dark:font-body dark:text-2xl dark:font-semibold">
            Bibliothèque
          </h1>
        </div>

        <div className="space-y-4">
          {(loginError ?? googleError) && (
            <p className="rounded-xl bg-red-100 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400">
              {loginError?.message ?? "Erreur lors de la connexion Google. Veuillez réessayer."}
            </p>
          )}

          <div className="flex justify-center">
            <GoogleLogin
              onError={() => setGoogleError(true)}
              onSuccess={(response) => {
                if (response.credential) {
                  login(response.credential);
                }
              }}
              shape="rectangular"
              size="large"
              text="signin_with"
              theme="filled_blue"
              width="300"
            />
          </div>

          {loginPending && (
            <p className="text-center text-sm text-text-muted">Connexion…</p>
          )}
        </div>
      </div>
    </div>
  );
}
