'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { clsx } from 'clsx';
import { LayoutDashboard, Inbox, Users, Building2, FileText, ScrollText, LogOut, User, Bell, BarChart2 } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { authApi } from '@/lib/api';

export function AdminSidebar() {
  const pathname = usePathname();
  const router   = useRouter();
  const { user, clearAuth, isSuperAdmin } = useAuthStore();

  const handleLogout = async () => {
    try { await authApi.logout(); } catch {}
    clearAuth();
    router.push('/login');
  };

  const mainLinks = [
    { href: '/admin/dashboard',   label: 'Dashboard',    Icon: LayoutDashboard },
    { href: '/admin/submissions', label: 'Inbox',        Icon: Inbox },
    { href: '/admin/notifications', label: 'Notifications', Icon: Bell },
  ];

  const analyticsLinks = isSuperAdmin() ? [
    { href: '/admin/analytics', label: 'Analytics', Icon: BarChart2 },
  ] : [];

  const settingsLinks = isSuperAdmin() ? [
    { href: '/admin/settings/departments', label: 'Departments', Icon: Building2 },
    { href: '/admin/settings/form-types',  label: 'Form Types',  Icon: FileText },
    { href: '/admin/settings/users',       label: 'Users',       Icon: Users },
    { href: '/admin/settings/audit-logs',  label: 'Audit Logs',  Icon: ScrollText },
  ] : [];

  const isActive = (href: string) => pathname === href || pathname.startsWith(href + '/');

  return (
    <aside className="hidden md:flex flex-col w-64 bg-brand-500 min-h-screen">
      {/* Logo */}
      <div className="px-6 py-5 border-b border-white/10">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
            <span className="text-white text-sm font-bold">S</span>
          </div>
          <div>
            <div className="text-sm font-bold text-white">StudentsHub</div>
            <div className="text-xs text-blue-200 capitalize">{user?.role?.replace('_', ' ')} Panel</div>
          </div>
        </div>
      </div>

      <nav className="flex-1 px-3 py-4 space-y-1">
        {mainLinks.map(({ href, label, Icon }) => (
          <Link
            key={href}
            href={href}
            className={clsx('sidebar-link', isActive(href)
              ? 'bg-white/20 text-white'
              : 'text-blue-100 hover:bg-white/10 hover:text-white'
            )}
          >
            <Icon className="w-4 h-4 shrink-0" />
            {label}
          </Link>
        ))}

        {analyticsLinks.length > 0 && (
          <>
            <div className="px-3 pt-4 pb-1 text-xs font-semibold text-blue-300 uppercase tracking-wider">Reports</div>
            {analyticsLinks.map(({ href, label, Icon }) => (
              <Link
                key={href}
                href={href}
                className={clsx('sidebar-link', isActive(href)
                  ? 'bg-white/20 text-white'
                  : 'text-blue-100 hover:bg-white/10 hover:text-white'
                )}
              >
                <Icon className="w-4 h-4 shrink-0" />
                {label}
              </Link>
            ))}
          </>
        )}

        {settingsLinks.length > 0 && (
          <>
            <div className="px-3 pt-4 pb-1 text-xs font-semibold text-blue-300 uppercase tracking-wider">Settings</div>
            {settingsLinks.map(({ href, label, Icon }) => (
              <Link
                key={href}
                href={href}
                className={clsx('sidebar-link', isActive(href)
                  ? 'bg-white/20 text-white'
                  : 'text-blue-100 hover:bg-white/10 hover:text-white'
                )}
              >
                <Icon className="w-4 h-4 shrink-0" />
                {label}
              </Link>
            ))}
          </>
        )}
      </nav>

      <div className="px-3 py-4 border-t border-white/10 space-y-1">
        <div className="flex items-center gap-3 px-3 py-2">
          <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
            <User className="w-4 h-4 text-white" />
          </div>
          <div className="min-w-0">
            <div className="text-sm font-medium text-white truncate">{user?.name}</div>
            <div className="text-xs text-blue-200 truncate">{user?.department?.name ?? user?.email}</div>
          </div>
        </div>
        <button onClick={handleLogout} className="sidebar-link text-blue-100 hover:bg-white/10 hover:text-white w-full">
          <LogOut className="w-4 h-4" />
          Sign out
        </button>
      </div>
    </aside>
  );
}
