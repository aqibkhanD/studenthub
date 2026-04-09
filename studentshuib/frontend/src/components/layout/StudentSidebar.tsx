'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { clsx } from 'clsx';
import { LayoutDashboard, FileText, ClipboardList, Bell, LogOut, User, UserCircle } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { authApi } from '@/lib/api';

const links = [
  { href: '/student/dashboard',      label: 'Dashboard',        Icon: LayoutDashboard },
  { href: '/student/forms',          label: 'Submit a Request',  Icon: FileText },
  { href: '/student/submissions',    label: 'My Submissions',    Icon: ClipboardList },
  { href: '/student/notifications',  label: 'Notifications',     Icon: Bell },
  { href: '/student/profile',        label: 'My Profile',        Icon: UserCircle },
];

export function StudentSidebar() {
  const pathname  = usePathname();
  const router    = useRouter();
  const { user, clearAuth } = useAuthStore();

  const handleLogout = async () => {
    try { await authApi.logout(); } catch {}
    clearAuth();
    router.push('/login');
  };

  return (
    <aside className="hidden md:flex flex-col w-64 bg-white border-r border-gray-100 min-h-screen">
      {/* Logo */}
      <div className="px-6 py-5 border-b border-gray-100">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center">
            <span className="text-white text-sm font-bold">S</span>
          </div>
          <div>
            <div className="text-sm font-bold text-gray-900">StudentsHub</div>
            <div className="text-xs text-gray-400">DIU Student Portal</div>
          </div>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label, Icon }) => (
          <Link
            key={href}
            href={href}
            className={clsx('sidebar-link', pathname.startsWith(href) ? 'sidebar-link-active' : 'sidebar-link-inactive')}
          >
            <Icon className="w-4 h-4 shrink-0" />
            {label}
          </Link>
        ))}
      </nav>

      {/* User */}
      <div className="px-3 py-4 border-t border-gray-100 space-y-1">
        <div className="flex items-center gap-3 px-3 py-2">
          <div className="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center">
            <User className="w-4 h-4 text-brand-500" />
          </div>
          <div className="min-w-0">
            <div className="text-sm font-medium text-gray-900 truncate">{user?.name}</div>
            <div className="text-xs text-gray-400 truncate">{user?.student_id ?? user?.email}</div>
          </div>
        </div>
        <button onClick={handleLogout} className="sidebar-link sidebar-link-inactive w-full text-red-600 hover:bg-red-50">
          <LogOut className="w-4 h-4" />
          Sign out
        </button>
      </div>
    </aside>
  );
}
