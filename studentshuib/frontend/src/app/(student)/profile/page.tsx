'use client';

import { useState, useEffect } from 'react';
import { useAuthStore } from '@/store/authStore';
import { authApi } from '@/lib/api';
import { Card, CardBody, Spinner } from '@/components/ui';
import { User, Lock, Save, Eye, EyeOff, CheckCircle, AlertCircle } from 'lucide-react';

// ---- Types ----------------------------------------------------------------

interface ProfileForm {
  name: string;
  phone: string;
  program: string;
  batch: string;
  semester: string;
}

interface PasswordForm {
  current_password: string;
  password: string;
  password_confirmation: string;
}

// ---- Alert helper ---------------------------------------------------------

function Alert({ type, message }: { type: 'success' | 'error'; message: string }) {
  const isSuccess = type === 'success';
  return (
    <div className={`flex items-center gap-2 text-sm px-3 py-2 rounded-lg ${
      isSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
    }`}>
      {isSuccess ? <CheckCircle className="w-4 h-4 shrink-0" /> : <AlertCircle className="w-4 h-4 shrink-0" />}
      {message}
    </div>
  );
}

// ---- Page -----------------------------------------------------------------

export default function StudentProfilePage() {
  const { user, setAuth } = useAuthStore();

  // Profile form state
  const [profile, setProfile] = useState<ProfileForm>({
    name:     user?.name     ?? '',
    phone:    user?.phone    ?? '',
    program:  user?.program  ?? '',
    batch:    user?.batch    ?? '',
    semester: user?.semester ?? '',
  });
  const [profileSaving, setProfileSaving]     = useState(false);
  const [profileAlert, setProfileAlert]       = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Password form state
  const [passwords, setPasswords] = useState<PasswordForm>({
    current_password:      '',
    password:              '',
    password_confirmation: '',
  });
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew]         = useState(false);
  const [pwSaving, setPwSaving]       = useState(false);
  const [pwAlert, setPwAlert]         = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Sync user into profile form if auth store updates
  useEffect(() => {
    if (user) {
      setProfile({
        name:     user.name     ?? '',
        phone:    user.phone    ?? '',
        program:  user.program  ?? '',
        batch:    user.batch    ?? '',
        semester: user.semester ?? '',
      });
    }
  }, [user]);

  // ---- Handlers -----------------------------------------------------------

  const handleProfileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setProfile((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setProfileSaving(true);
    setProfileAlert(null);
    try {
      await authApi.updateProfile(profile);
      // Refresh auth store with latest user data
      const meRes = await authApi.me();
      const updatedUser = meRes.data.user;
      // Preserve the existing token from storage
      const token = typeof window !== 'undefined' ? localStorage.getItem('sh_token') : null;
      if (token) setAuth(updatedUser, token);
      setProfileAlert({ type: 'success', message: 'Profile updated successfully.' });
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to update profile.';
      setProfileAlert({ type: 'error', message: msg });
    } finally {
      setProfileSaving(false);
    }
  };

  const handlePasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setPasswords((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPwAlert(null);

    if (passwords.password !== passwords.password_confirmation) {
      setPwAlert({ type: 'error', message: 'New passwords do not match.' });
      return;
    }
    if (passwords.password.length < 8) {
      setPwAlert({ type: 'error', message: 'New password must be at least 8 characters.' });
      return;
    }

    setPwSaving(true);
    try {
      await authApi.changePassword(passwords);
      setPwAlert({ type: 'success', message: 'Password changed. Please use your new password next time you log in.' });
      setPasswords({ current_password: '', password: '', password_confirmation: '' });
    } catch (err: unknown) {
      const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      const msg = errors
        ? Object.values(errors).flat().join(' ')
        : (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to change password.';
      setPwAlert({ type: 'error', message: msg });
    } finally {
      setPwSaving(false);
    }
  };

  // ---- Render -------------------------------------------------------------

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-8">

      {/* Page header */}
      <div>
        <h1 className="text-xl font-bold text-gray-900">My Profile</h1>
        <p className="text-sm text-gray-500 mt-1">Manage your personal information and account security.</p>
      </div>

      {/* Account info banner */}
      <Card>
        <CardBody className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-full bg-brand-100 flex items-center justify-center shrink-0">
            <User className="w-7 h-7 text-brand-500" />
          </div>
          <div className="min-w-0">
            <div className="font-semibold text-gray-900 text-base">{user?.name}</div>
            <div className="text-sm text-gray-500 truncate">{user?.email}</div>
            {user?.student_id && (
              <div className="text-xs text-gray-400 font-mono mt-0.5">ID: {user.student_id}</div>
            )}
          </div>
          {user?.role && (
            <span className="ml-auto shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 capitalize">
              {user.role.replace('_', ' ')}
            </span>
          )}
        </CardBody>
      </Card>

      {/* Profile form */}
      <Card>
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="text-sm font-semibold text-gray-900">Personal Information</h2>
        </div>
        <CardBody>
          <form onSubmit={handleProfileSubmit} className="space-y-4">
            <div className="grid sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input
                  type="text"
                  name="name"
                  value={profile.name}
                  onChange={handleProfileChange}
                  className="input-field"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input
                  type="tel"
                  name="phone"
                  value={profile.phone}
                  onChange={handleProfileChange}
                  placeholder="01XXXXXXXXX"
                  className="input-field"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Program</label>
                <input
                  type="text"
                  name="program"
                  value={profile.program}
                  onChange={handleProfileChange}
                  placeholder="e.g. B.Sc. in CSE"
                  className="input-field"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Batch</label>
                <input
                  type="text"
                  name="batch"
                  value={profile.batch}
                  onChange={handleProfileChange}
                  placeholder="e.g. 2022"
                  className="input-field"
                />
              </div>
              <div className="sm:col-span-2">
                <label className="block text-sm font-medium text-gray-700 mb-1">Current Semester</label>
                <input
                  type="text"
                  name="semester"
                  value={profile.semester}
                  onChange={handleProfileChange}
                  placeholder="e.g. Spring 2026"
                  className="input-field"
                />
              </div>
            </div>

            {profileAlert && <Alert type={profileAlert.type} message={profileAlert.message} />}

            <div className="flex justify-end">
              <button
                type="submit"
                disabled={profileSaving}
                className="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white text-sm font-medium rounded-lg hover:bg-brand-600 disabled:opacity-60 transition-colors"
              >
                {profileSaving ? <Spinner className="w-4 h-4" /> : <Save className="w-4 h-4" />}
                Save Changes
              </button>
            </div>
          </form>
        </CardBody>
      </Card>

      {/* Password change */}
      <Card>
        <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
          <Lock className="w-4 h-4 text-gray-400" />
          <h2 className="text-sm font-semibold text-gray-900">Change Password</h2>
        </div>
        <CardBody>
          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            {/* Current password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
              <div className="relative">
                <input
                  type={showCurrent ? 'text' : 'password'}
                  name="current_password"
                  value={passwords.current_password}
                  onChange={handlePasswordChange}
                  className="input-field pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowCurrent((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  {showCurrent ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* New password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <div className="relative">
                <input
                  type={showNew ? 'text' : 'password'}
                  name="password"
                  value={passwords.password}
                  onChange={handlePasswordChange}
                  className="input-field pr-10"
                  placeholder="At least 8 characters"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowNew((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  {showNew ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Confirm password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <input
                type="password"
                name="password_confirmation"
                value={passwords.password_confirmation}
                onChange={handlePasswordChange}
                className="input-field"
                required
              />
            </div>

            {pwAlert && <Alert type={pwAlert.type} message={pwAlert.message} />}

            <div className="flex justify-end">
              <button
                type="submit"
                disabled={pwSaving}
                className="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 disabled:opacity-60 transition-colors"
              >
                {pwSaving ? <Spinner className="w-4 h-4" /> : <Lock className="w-4 h-4" />}
                Update Password
              </button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
