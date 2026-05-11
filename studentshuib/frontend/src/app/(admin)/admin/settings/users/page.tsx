'use client';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';
import { Card, Button, Input, Select, Spinner, EmptyState, useToast } from '@/components/ui';
import type { User, Department } from '@/types';
import { Users, Plus, UserX, UserCheck, Search, Trash2 } from 'lucide-react';
import { clsx } from 'clsx';
import { formatDistanceToNow } from 'date-fns';

type UserForm = { name: string; email: string; role: string; department_id: string; phone: string; password: string };
const EMPTY: UserForm = { name: '', email: '', role: 'admin', department_id: '', phone: '', password: '' };

const ROLE_OPTS = [
  { value: 'admin',       label: 'Admin' },
  { value: 'dept_head',   label: 'Dept Head' },
  { value: 'super_admin', label: 'Super Admin' },
  { value: 'management',  label: 'Management' },
];

export default function UsersSettingsPage() {
  const qc = useQueryClient();
  const { toast } = useToast();
  const currentUserId = useAuthStore((s) => s.user?.id);

  const [showForm,   setShowForm]   = useState(false);
  const [form,       setForm]       = useState<UserForm>(EMPTY);
  const [search,     setSearch]     = useState('');
  const [confirmDel, setConfirmDel] = useState<number | null>(null);

  // Backend returns Laravel paginated { data: [...], current_page, total }.
  const { data: users, isLoading } = useQuery({
    queryKey: ['admin-users'],
    queryFn:  () => superApi.users(),
    select:   (res) => ((res.data as { data?: User[] })?.data ?? []) as User[],
  });

  // Departments returns { departments: [...] } — keyed.
  const { data: depts } = useQuery({
    queryKey: ['departments'],
    queryFn:  () => superApi.departments(),
    select:   (res) => ((res.data as { departments?: Department[] })?.departments ?? []) as Department[],
  });

  const deptOpts = [
    { value: '', label: 'No department' },
    ...(depts ?? []).map(d => ({ value: String(d.id), label: d.name })),
  ];

  const showApiError = (err: unknown, fallback: string) => {
    const data = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data;
    if (data?.errors) {
      toast(Object.values(data.errors).flat().join(' '), 'error');
    } else if (data?.message) {
      toast(data.message, 'error');
    } else {
      toast(fallback, 'error');
    }
  };

  const createMut = useMutation({
    mutationFn: (data: UserForm) => superApi.createUser({
      ...data,
      department_id: data.department_id === '' ? null : Number(data.department_id),
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setShowForm(false); setForm(EMPTY);
      toast('User created', 'success');
    },
    onError: (err) => showApiError(err, 'Failed to create user'),
  });

  // Toggle Active — soft state flip, reversible. Different button from Delete.
  const toggleMut = useMutation({
    mutationFn: (id: number) => superApi.toggleUser(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-users'] }),
    onError:   (err) => showApiError(err, 'Failed to toggle user'),
  });

  // Delete — hard delete via destroy endpoint. Backend refuses if the user
  // has submissions on record (preserves audit history).
  const deleteMut = useMutation({
    mutationFn: (id: number) => superApi.deleteUser(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setConfirmDel(null);
      toast('User deleted', 'success');
    },
    onError: (err) => {
      showApiError(err, 'Failed to delete user');
      setConfirmDel(null);
    },
  });

  const displayed = (users ?? []).filter(u =>
    !search || u.name.toLowerCase().includes(search.toLowerCase()) || u.email.toLowerCase().includes(search.toLowerCase())
  ).filter(u => u.role !== 'student'); // only show staff

  const roleBadge = (role: string) => {
    const map: Record<string, string> = {
      super_admin: 'bg-purple-100 text-purple-700',
      admin:       'bg-blue-100 text-blue-700',
      dept_head:   'bg-indigo-100 text-indigo-700',
      management:  'bg-gray-100 text-gray-700',
      student:     'bg-green-100 text-green-700',
    };
    return map[role] ?? 'bg-gray-100 text-gray-600';
  };

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h1 className="text-xl font-bold text-gray-900">Staff Users</h1>
        <Button size="sm" onClick={() => { setShowForm(true); setForm(EMPTY); }}>
          <Plus className="w-4 h-4" /> Add User
        </Button>
      </div>

      {/* Create form */}
      {showForm && (
        <Card>
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-900">New Staff User</h2>
          </div>
          <div className="p-6 grid sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Full Name *</label>
              <Input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="Full name" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Email *</label>
              <Input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} placeholder="staff@diu.edu.bd" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Role *</label>
              <Select options={ROLE_OPTS} value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))} />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Department</label>
              <Select options={deptOpts} value={form.department_id} onChange={e => setForm(f => ({ ...f, department_id: e.target.value }))} />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Phone</label>
              <Input value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} placeholder="01XXXXXXXXX" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Temporary Password *</label>
              <Input type="password" value={form.password} onChange={e => setForm(f => ({ ...f, password: e.target.value }))} placeholder="Min 8 characters" />
            </div>
          </div>
          <div className="px-6 pb-5 flex gap-2">
            <Button onClick={() => createMut.mutate(form)} loading={createMut.isPending}>Create User</Button>
            <Button variant="ghost" onClick={() => setShowForm(false)}>Cancel</Button>
          </div>
        </Card>
      )}

      {/* Search */}
      <div className="relative w-72">
        <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
        <input
          className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          placeholder="Search by name or email..."
          value={search}
          onChange={e => setSearch(e.target.value)}
        />
      </div>

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : displayed.length === 0 ? (
          <EmptyState title="No staff users" description="Add your first staff member." icon={Users} />
        ) : (
          <>
            <div className="hidden md:grid grid-cols-12 gap-4 px-6 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <div className="col-span-3">Name</div>
              <div className="col-span-3">Email</div>
              <div className="col-span-2">Role</div>
              <div className="col-span-2">Department</div>
              <div className="col-span-1">Last seen</div>
              <div className="col-span-1" />
            </div>
            <ul className="divide-y divide-gray-50">
              {displayed.map((u) => {
                const isSelf = u.id === currentUserId;
                return (
                <li key={u.id} className={clsx('grid md:grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-gray-50 transition-colors', !u.is_active && 'opacity-50')}>
                  <div className="md:col-span-3 text-sm font-medium text-gray-800">
                    {u.name}
                    {isSelf && <span className="ml-2 text-xs font-normal text-gray-400">(you)</span>}
                  </div>
                  <div className="md:col-span-3 text-sm text-gray-500 truncate">{u.email}</div>
                  <div className="md:col-span-2">
                    <span className={clsx('text-xs font-medium px-2 py-0.5 rounded-full capitalize', roleBadge(u.role))}>
                      {u.role.replace('_', ' ')}
                    </span>
                  </div>
                  <div className="md:col-span-2 text-sm text-gray-500">{u.department?.name ?? '—'}</div>
                  <div className="md:col-span-1 text-xs text-gray-400">
                    {u.last_login_at ? formatDistanceToNow(new Date(u.last_login_at), { addSuffix: true }) : 'Never'}
                  </div>
                  <div className="md:col-span-1 flex justify-end items-center gap-2">
                    {confirmDel === u.id ? (
                      <>
                        <button
                          onClick={() => deleteMut.mutate(u.id)}
                          disabled={deleteMut.isPending}
                          className="text-xs font-medium text-white bg-red-500 hover:bg-red-600 px-2 py-1 rounded disabled:opacity-50"
                          title="Permanently delete this user"
                        >
                          Confirm
                        </button>
                        <button
                          onClick={() => setConfirmDel(null)}
                          className="text-xs font-medium text-gray-500 hover:text-gray-700 px-2 py-1 rounded"
                        >
                          Cancel
                        </button>
                      </>
                    ) : (
                      <>
                        <button
                          onClick={() => !isSelf && toggleMut.mutate(u.id)}
                          disabled={isSelf}
                          className={clsx(
                            'transition-colors',
                            isSelf
                              ? 'text-gray-200 cursor-not-allowed'
                              : u.is_active
                                ? 'text-gray-400 hover:text-yellow-500'
                                : 'text-gray-300 hover:text-green-500'
                          )}
                          title={isSelf ? 'You cannot deactivate your own account' : (u.is_active ? 'Deactivate (reversible)' : 'Activate')}
                        >
                          {u.is_active ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                        </button>
                        <button
                          onClick={() => !isSelf && setConfirmDel(u.id)}
                          disabled={isSelf}
                          className={clsx(
                            'transition-colors',
                            isSelf ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-red-500'
                          )}
                          title={isSelf ? 'You cannot delete your own account' : 'Permanently delete user (refused if they have submissions on record)'}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </>
                    )}
                  </div>
                </li>
                );
              })}
            </ul>
          </>
        )}
      </Card>
    </div>
  );
}
