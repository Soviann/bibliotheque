import { GoogleLogin } from "@react-oauth/google";
import { useAuth } from "../hooks/useAuth";

export default function Login() {
  const { login, loginError, loginPending } = useAuth();

  return (
    <div className="flex min-h-screen items-center justify-center bg-surface-primary px-4 dark:bg-surface-secondary">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <img alt="" className="mx-auto h-20 w-20 rounded-2xl" src="/app-icon.png" />
          <h1 className="mt-4 text-2xl font-bold text-text-primary">Bibliothèque</h1>
        </div>

        <div className="space-y-4">
          {loginError && (
            <p className="rounded-lg bg-red-100 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400">
              {loginError.message}
            </p>
          )}

          <div className="flex justify-center">
            <GoogleLogin
              onError={() => login("" as string)}
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
