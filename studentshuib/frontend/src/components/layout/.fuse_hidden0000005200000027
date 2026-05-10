'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { clsx } from 'clsx';
import { LayoutDashboard, FileText, ClipboardList, Bell, LogOut, User, UserCircle } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { authApi } from '@/lib/api';

// IMPORTANT: student URLs do NOT include a `/student/` prefix.
// The (student) route group is invisible — `(student)/dashboard/page.tsx`
// resolves to `/dashboard`, not `/student/dashboard`. Earlier code had the
// wrong URLs which 404'd; do not re-introduce the prefix.
const links = [
  { href: '/dashboard',     label: 'Dashboard',        Icon: LayoutDashboard },
  { href: '/forms',         label: 'Submit a Request', Icon: FileText },
  { href: '/submissions',   label: 'My Submissions',   Icon: ClipboardList },
  { href: '/notifications', label: 'Notifications',    Icon: Bell },
  { href: '/profile',       label: 'My Profile',       Icon: UserCircle },
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

  // Active if exact match or path begins with `${href}/` — avoids `/profile`
  // matching `/profile-anything` while still highlighting nested routes.
  const isActive = (href: string) => pathname === href || pathname.startsWith(href + '/');

  return (
    <aside className="hidden md:flex flex-col w-60 bg-white border-r border-gray-100 min-h-screen">
      {/* Logo */}
      <div className="px-5 py-4 border-b border-gray-100">
        <div className="flex items-center gap-3">
          <div className="w-7 h-7 rounded-lg bg-brand-500 flex items-center justify-center">
            <span className="text-white text-[13px] font-bold">S</span>
          </div>
          <div>
            <div className="text-[13px] font-bold text-gray-900">StudentsHub</div>
            <div className="text-[11px] text-gray-400">DIU Student Portal</div>
          </div>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label, Icon }) => (
          <Link
            key={href}
            href={href}
            className={clsx('sidebar-link',
              isActive(href) ? 'sidebar-link-active' : 'sidebar-link-inactive'
            )}
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
            <div className="text-[13px] font-medium text-gray-900 truncate">{user?.name}</div>
            <div className="text-[11px] text-gray-400 truncate">
              {user?.student_id ?? user?.email}
            </div>
          </div>
        </div>
        <button
          onClick={handleLogout}
          className="sidebar-link sidebar-link-inactive w-full text-red-600 hover:bg-red-50"
        >
          <LogOut className="w-4 h-4" />
          Sign out
        </button>
      </div>
    </aside>
  );
}
