'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { AdminSidebar } from '@/components/layout/AdminSidebar';
import { Spinner } from '@/components/ui';

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { isAuthenticated, isAdmin } = useAuthStore();
  // Wait for zustand to hydrate from localStorage before checking auth.
  // Without this, isAuthenticated is always false on first render (server default),
  // causing an immediate redirect to /login even when the user is logged in.
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => { setHydrated(true); }, []);

  useEffect(() => {
    if (!hydrated) return;
    if (!isAuthenticated) { router.push('/login'); return; }
    // Student dashboard URL is `/dashboard` (route group `(student)` is invisible),
    // NOT `/student/dashboard`.
    if (!isAdmin())       { router.push('/dashboard'); }
  }, [hydrated, isAuthenticated, isAdmin, router]);

  if (!hydrated || !isAuthenticated) {
    return <div className="min-h-screen flex items-center justify-center"><Spinner /></div>;
  }

  return (
    // h-screen + overflow-hidden locks the page to viewport height so the
    // sidebar stays at 100vh and its footer (Sign out, Student Portal, user
    // info) is always visible. The <main> handles its own vertical scroll.
    <div className="flex h-screen overflow-hidden">
      <AdminSidebar />
      <main className="flex-1 overflow-auto bg-gray-50">{children}</main>
    </div>
  );
}
