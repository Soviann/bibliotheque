import { useMutation } from "@tanstack/react-query";
import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import {
  isAuthenticated,
  login as apiLogin,
  removeToken,
} from "../services/api";

export function useAuth() {
  const navigate = useNavigate();

  const loginMutation = useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) =>
      apiLogin(email, password),
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
