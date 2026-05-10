'use client';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { Card, Button, Input, Spinner, useToast } from '@/components/ui';
import type { SystemSettings } from '@/types';
import { Settings as SettingsIcon, Calendar } from 'lucide-react';

// ----------------------------------------------------------------
// System Settings — currently just the academic semester window
// (label + start/end dates). The semester window drives the
// "Semester" period selector on the Managerial Overview dashboard.
// ----------------------------------------------------------------

type SemesterForm = { label: string; start_date: string; end_date: string };
const EMPTY: SemesterForm = { label: '', start_date: '', end_date: '' };

export default function SystemSettingsPage() {
  const qc = useQueryClient();
  const { toast } = useToast();
  const [form, setForm] = useState<SemesterForm>(EMPTY);

  const { data: settings, isLoading } = useQuery({
    queryKey: ['system-settings'],
    queryFn:  () => superApi.systemSettings(),
    select:   (res) => res.data as SystemSettings,
  });

  // Sync form with loaded settings
  useEffect(() => {
    if (!settings?.semester) return;
    setForm({
      label:      settings.semester.label ?? '',
      start_date: settings.semester.start_date ?? '',
      end_date:   settings.semester.end_date ?? '',
    });
  }, [settings]);

  const saveMut = useMutation({
    mutationFn: (data: SemesterForm) => superApi.updateSystemSettings({
      semester: {
        label:      data.label,
        start_date: data.start_date,
        end_date:   data.end_date,
      },
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['system-settings'] });
      qc.invalidateQueries({ queryKey: ['admin-dashboard'] });
      toast({ title: 'Settings saved.', variant: 'success' });
    },
    onError: (err: unknown) => {
      const data = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data;
      if (data?.errors) {
        toast({ title: Object.values(data.errors).flat().join(' '), variant: 'error' });
      } else {
        toast({ title: data?.message ?? 'Failed to save settings.', variant: 'error' });
      }
    },
  });

  const canSave =
    form.label.trim() !== '' &&
    form.start_date !== '' &&
    form.end_date   !== '' &&
    form.end_date  >= form.start_date;

  return (
    <div className="p-6 space-y-5 max-w-2xl">
      <div className="flex items-center gap-3">
        <SettingsIcon className="w-5 h-5 text-gray-400" />
        <h1 className="text-xl font-bold text-gray-900">System Settings</h1>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-16"><Spinner /></div>
      ) : (
        <Card>
          <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <Calendar className="w-4 h-4 text-gray-400" />
            <h2 className="text-sm font-semibold text-gray-900">Academic Semester</h2>
          </div>
          <div className="p-6 space-y-4">
            <p className="text-sm text-gray-500">
              The current academic semester drives the &quot;Semester&quot; period
              filter on the Managerial Overview dashboard. Update these when
              the semester rolls over.
            </p>

            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Semester Label *</label>
              <Input
                value={form.label}
                onChange={e => setForm(f => ({ ...f, label: e.target.value }))}
                placeholder="e.g. Spring 2026"
                maxLength={50}
              />
              <p className="text-xs text-gray-400 mt-1">Shown as the dashboard subtitle.</p>
            </div>

            <div className="grid sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Start Date *</label>
                <Input
                  type="date"
                  value={form.start_date}
                  onChange={e => setForm(f => ({ ...f, start_date: e.target.value }))}
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">End Date *</label>
                <Input
                  type="date"
                  value={form.end_date}
                  onChange={e => setForm(f => ({ ...f, end_date: e.target.value }))}
                />
              </div>
            </div>
            {form.start_date && form.end_date && form.end_date < form.start_date && (
              <p className="text-xs text-red-600">End date must be on or after the start date.</p>
            )}
          </div>
          <div className="px-6 pb-5 flex gap-2">
            <Button
              onClick={() => saveMut.mutate(form)}
              loading={saveMut.isPending}
              disabled={!canSave}
            >
              Save Changes
            </Button>
          </div>
        </Card>
      )}
    </div>
  );
}
