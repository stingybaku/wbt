import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import { logout as apiLogout, setUnauthorizedHandler } from '../lib/api';
import type { User } from '../lib/types';

const TOKEN_KEY = 'wbt.auth.token';
const USER_KEY = 'wbt.auth.user';

type AuthContextValue = {
  token: string | null;
  user: User | null;
  setAuth: (token: string, user: User) => void;
  clearAuth: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function readStoredAuth(): { token: string | null; user: User | null } {
  const token = localStorage.getItem(TOKEN_KEY);
  const rawUser = localStorage.getItem(USER_KEY);
  if (!token || !rawUser) return { token: null, user: null };
  try {
    return { token, user: JSON.parse(rawUser) as User };
  } catch {
    return { token: null, user: null };
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const stored = readStoredAuth();
  const [token, setToken] = useState<string | null>(stored.token);
  const [user, setUser] = useState<User | null>(stored.user);

  function setAuth(newToken: string, newUser: User) {
    localStorage.setItem(TOKEN_KEY, newToken);
    localStorage.setItem(USER_KEY, JSON.stringify(newUser));
    setToken(newToken);
    setUser(newUser);
  }

  function clearAuth() {
    if (token) {
      apiLogout(token).catch(() => {});
    }
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    setToken(null);
    setUser(null);
  }

  // Silently clears auth without calling the logout API (used when the token is
  // already invalid, e.g. expired on the backend).
  function expireAuth() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    setToken(null);
    setUser(null);
  }

  useEffect(() => {
    setUnauthorizedHandler(expireAuth);
    return () => setUnauthorizedHandler(null);
  }, []);

  return (
    <AuthContext.Provider value={{ token, user, setAuth, clearAuth }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
