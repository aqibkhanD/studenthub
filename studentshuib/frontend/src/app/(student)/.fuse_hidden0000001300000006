'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { StudentSidebar } from '@/components/layout/StudentSidebar';
import { Spinner } from '@/components/ui';

export default function StudentLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { isAuthenticated, isStudent } = useAuthStore();
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => { setHydrated(true); }, []);

  useEffect(() => {
    if (!hydrated) return;
    if (!isAuthenticated) { router.push('/login'); return; }
    if (!isStudent())     { router.push('/admin/dashboard'); }
  }, [hydrated, isAuthenticated, isStudent, router]);

  if (!hydrated || !isAuthenticated) {
    return <div className="min-h-screen flex items-center justify-center"><Spinner /></div>;
  }

  return (
    // h-screen + overflow-hidden locks the page to viewport height so the
    // sidebar's footer (Sign out, user info) stays visible on long pages.
    <div className="flex h-screen overflow-hidden">
      <StudentSidebar />
      <main className="flex-1 overflow-auto">
        {children}
      </main>
    </div>
  );
}
