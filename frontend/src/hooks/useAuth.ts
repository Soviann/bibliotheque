import { useMutation } from "@tanstack/react-query";
import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import {
  isAuthenticated,
  loginWithGoogle as apiLoginWithGoogle,
  removeToken,
} from "../services/api";

export function useAuth() {
  const navigate = useNavigate();

  const loginMutation = useMutation({
    mutationFn: (credential: string) => apiLoginWithGoogle(credential),
    onSuccess: () => {
      navigate("/");
    },
  });

  const logout = useCallback(() => {
    removeToken();
    navigate("/login");
  }, [navigate]);

  return {
    isAuthenticated: isAuthenticated(),
    login: loginMutation.mutate,
    loginError: loginMutation.error,
    loginPending: loginMutation.isPending,
    logout,
  };
}
