import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { isAuthenticated } from "../services/api";

interface AuthGuardProps {
  children: ReactNode;
}

export default function AuthGuard({ children }: AuthGuardProps) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
