import { useQueryClient, useMutation } from "@tanstack/react-query";
import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { del } from "idb-keyval";
import {
  isAuthenticated,
  loginWithDev as apiLoginWithDev,
  loginWithGoogle as apiLoginWithGoogle,
  removeToken,
} from "../services/api";

export function useAuth() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const loginMutation = useMutation({
    mutationFn: (credential: string) => apiLoginWithGoogle(credential),
    onSuccess: () => {
      navigate("/", { viewTransition: true });
    },
  });

  const devLoginMutation = useMutation({
    mutationFn: ({
      username,
      password,
    }: {
      username: string;
      password: string;
    }) => apiLoginWithDev(username, password),
    onSuccess: () => {
      navigate("/", { viewTransition: true });
    },
  });

  const logout = useCallback(() => {
    removeToken();
    navigate("/login", { viewTransition: true });
    // Defer cache clearing — the user already sees the login page
    setTimeout(() => {
      queryClient.clear();
      void del("bibliotheque-query-cache");
      void caches.delete("api-cache");
    }, 0);
  }, [navigate, queryClient]);

  return {
    devLogin: devLoginMutation.mutate,
    devLoginError: devLoginMutation.error,
    devLoginPending: devLoginMutation.isPending,
    isAuthenticated: isAuthenticated(),
    login: loginMutation.mutate,
    loginError: loginMutation.error,
    loginPending: loginMutation.isPending,
    logout,
  };
}
