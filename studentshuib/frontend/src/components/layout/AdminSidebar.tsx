'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { clsx } from 'clsx';
import {
  LayoutDashboard, Inbox, Users, Building2, FileText, ScrollText,
  LogOut, User, Bell, BarChart2, ExternalLink, Settings,
} from 'lucide-react';
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
    { href: '/admin/dashboard',     label: 'Overview',         Icon: LayoutDashboard },
    { href: '/admin/submissions',   label: 'Submission Queue', Icon: Inbox },
    { href: '/admin/notifications', label: 'Notifications',    Icon: Bell },
  ];

  const analyticsLinks = isSuperAdmin() ? [
    { href: '/admin/analytics', label: 'Analytics', Icon: BarChart2 },
  ] : [];

  const settingsLinks = isSuperAdmin() ? [
    { href: '/admin/settings/departments', label: 'Departments', Icon: Building2 },
    { href: '/admin/settings/form-types',  label: 'Form Types',  Icon: FileText },
    { href: '/admin/settings/users',       label: 'Staff Users', Icon: Users },
    { href: '/admin/settings/audit-logs',  label: 'Audit Logs',  Icon: ScrollText },
    { href: '/admin/settings/system',      label: 'System',      Icon: Settings },
  ] : [];

  // Active when the URL exactly matches OR begins with the link href + '/'
  // (e.g., on /admin/submissions/DIU-2026-00001 the Submission Queue link is active).
  const isActive = (href: string) => pathname === href || pathname.startsWith(href + '/');

  const linkCls = (href: string) =>
    clsx('sidebar-link', isActive(href)
      ? 'bg-white/20 text-white shadow-inner shadow-black/10'
      : 'text-blue-100 hover:bg-white/10 hover:text-white'
    );

  return (
    <aside className="hidden md:flex flex-col w-60 bg-brand-500 min-h-screen">
      {/* Branding */}
      <div className="px-5 py-4 border-b border-white/10">
        <div className="text-[13px] font-bold text-white">StudentsHub</div>
        <div className="text-[11px] text-blue-200 mt-0.5">Daffodil International University</div>
        <div className="text-[11px] text-blue-300 mt-1 capitalize font-medium">
          {user?.role?.replace('_', ' ')} Panel
        </div>
      </div>

      {/* Main nav */}
      <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        {mainLinks.map(({ href, label, Icon }) => (
          <Link key={href} href={href} className={linkCls(href)}>
            <Icon className="w-4 h-4 shrink-0" />
            {label}
          </Link>
        ))}

        {analyticsLinks.length > 0 && (
          <>
            <div className="px-3 pt-5 pb-1 text-[11px] font-semibold text-blue-300 uppercase tracking-wider">
              Reports
            </div>
            {analyticsLinks.map(({ href, label, Icon }) => (
              <Link key={href} href={href} className={linkCls(href)}>
                <Icon className="w-4 h-4 shrink-0" />
                {label}
              </Link>
            ))}
          </>
        )}

        {settingsLinks.length > 0 && (
          <>
            <div className="px-3 pt-5 pb-1 text-[11px] font-semibold text-blue-300 uppercase tracking-wider">
              Administration
            </div>
            {settingsLinks.map(({ href, label, Icon }) => (
              <Link key={href} href={href} className={linkCls(href)}>
                <Icon className="w-4 h-4 shrink-0" />
                {label}
              </Link>
            ))}
          </>
        )}
      </nav>

      {/* Footer — user info + actions */}
      <div className="px-3 py-4 border-t border-white/10 space-y-1">
        {/* Logged-in user */}
        <div className="flex items-center gap-3 px-3 py-2 mb-1">
          <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <User className="w-4 h-4 text-white" />
          </div>
          <div className="min-w-0">
            <div className="text-[13px] font-medium text-white truncate">{user?.name}</div>
            <div className="text-[11px] text-blue-200 truncate">
              {user?.department?.name ?? user?.email}
            </div>
          </div>
        </div>

        {/* Student portal link — opens the student-facing app in a new tab so
            super admins can preview what students see without losing context. */}
        <a
          href="/dashboard"
          target="_blank"
          rel="noopener noreferrer"
          className="sidebar-link text-blue-100 hover:bg-white/10 hover:text-white w-full"
        >
          <ExternalLink className="w-4 h-4 shrink-0" />
          Student Portal
        </a>

        {/* Sign out — styled red so it stands apart from navigation */}
        <button
          onClick={handleLogout}
          className="sidebar-link text-red-300 hover:bg-red-900/30 hover:text-red-100 w-full"
        >
          <LogOut className="w-4 h-4 shrink-0" />
          Sign out
        </button>
      </div>
    </aside>
  );
}
