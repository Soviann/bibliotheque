import { useQueryClient, useMutation } from "@tanstack/react-query";
import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { del } from "idb-keyval";
import {
  isAuthenticated,
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

  const logout = useCallback(() => {
    queryClient.clear();
    del("bibliotheque-query-cache");
    removeToken();
    navigate("/login", { viewTransition: true });
  }, [navigate, queryClient]);

  return {
    isAuthenticated: isAuthenticated(),
    login: loginMutation.mutate,
    loginError: loginMutation.error,
    loginPending: loginMutation.isPending,
    logout,
  };
}
