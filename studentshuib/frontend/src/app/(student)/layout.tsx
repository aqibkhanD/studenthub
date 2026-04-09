'use client';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { StudentSidebar } from '@/components/layout/StudentSidebar';
import { Spinner } from '@/components/ui';

export default function StudentLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { isAuthenticated, isStudent } = useAuthStore();

  useEffect(() => {
    if (!isAuthenticated) { router.push('/login'); return; }
    if (!isStudent())     { router.push('/admin/dashboard'); }
  }, [isAuthenticated, isStudent, router]);

  if (!isAuthenticated) {
    return <div className="min-h-screen flex items-center justify-center"><Spinner /></div>;
  }

  return (
    <div className="flex min-h-screen">
      <StudentSidebar />
      <main className="flex-1 overflow-auto">
        {children}
      </main>
    </div>
  );
}
