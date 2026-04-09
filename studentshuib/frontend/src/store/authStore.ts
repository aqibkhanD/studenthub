'use client';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types';

interface AuthState {
  user: User | null;
  token: string | null;
  setAuth: (user: User, token: string) => void;
  clearAuth: () => void;
  isAuthenticated: boolean;
  isAdmin: () => boolean;
  isSuperAdmin: () => boolean;
  isStudent: () => boolean;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user:  null,
      token: null,
      isAuthenticated: false,

      setAuth: (user, token) => {
        if (typeof window !== 'undefined') localStorage.setItem('sh_token', token);
        set({ user, token, isAuthenticated: true });
      },

      clearAuth: () => {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('sh_token');
          localStorage.removeItem('sh_user');
        }
        set({ user: null, token: null, isAuthenticated: false });
      },

      isAdmin:      () => ['admin', 'dept_head', 'super_admin', 'management'].includes(get().user?.role ?? ''),
      isSuperAdmin: () => get().user?.role === 'super_admin',
      isStudent:    () => get().user?.role === 'student',
    }),
    {
      name: 'sh_auth',
      partialize: (state) => ({ user: state.user, token: state.token, isAuthenticated: state.isAuthenticated }),
    }
  )
);
