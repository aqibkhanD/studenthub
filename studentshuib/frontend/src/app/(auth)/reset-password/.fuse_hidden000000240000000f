'use client';
import { useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { authApi } from '@/lib/api';
import { Button, Input } from '@/components/ui';
import Link from 'next/link';
import { KeyRound, ArrowLeft, CheckCircle, Eye, EyeOff } from 'lucide-react';

interface ResetForm {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

// Inner component — uses useSearchParams (must be in Suspense boundary)
function ResetPasswordForm() {
  const router        = useRouter();
  const searchParams  = useSearchParams();
  const prefillEmail  = searchParams.get('email') ?? '';

  const [done, setDone]       = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');
  const [showPw, setShowPw]   = useState(false);

  const { register, handleSubmit, watch, formState: { errors } } = useForm<ResetForm>({
    defaultValues: { email: prefillEmail },
  });

  const onSubmit = async (data: ResetForm) => {
    setLoading(true);
    setError('');
    try {
      await authApi.resetPassword(data.email, data.token, data.password, data.password_confirmation);
      setDone(true);
      // Auto-redirect to login after 3 seconds
      setTimeout(() => router.push('/login'), 3000);
    } catch (err: unknown) {
      const errData = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data;
      const msg = errData?.errors
        ? Object.values(errData.errors).flat().join(' ')
        : errData?.message ?? 'Invalid or expired reset code. Please try again.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  if (done) {
    return (
      <div className="text-center space-y-4">
        <div className="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto">
          <CheckCircle className="w-7 h-7 text-green-500" />
        </div>
        <h2 className="text-lg font-semibold text-gray-900">Password reset</h2>
        <p className="text-sm text-gray-500">
          Your password has been updated. Redirecting you to the sign-in page...
        </p>
        <Link href="/login" className="block text-sm text-brand-500 hover:underline">
          Go to sign in now
        </Link>
      </div>
    );
  }

  return (
    <>
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center shrink-0">
          <KeyRound className="w-5 h-5 text-brand-500" />
        </div>
        <div>
          <h2 className="text-lg font-semibold text-gray-900">Set new password</h2>
          <p className="text-xs text-gray-400 mt-0.5">Enter the 6-digit code from your email.</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="Email address"
          type="email"
          placeholder="you@diu.edu.bd"
          required
          error={errors.email?.message}
          {...register('email', { required: 'Email is required' })}
        />

        {/* OTP code — large, numeric-only input */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Reset code <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            inputMode="numeric"
            maxLength={6}
            placeholder="000000"
            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-2xl tracking-[0.5em] text-center font-mono text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
            {...register('token', {
              required: 'Reset code is required',
              pattern:  { value: /^\d{6}$/, message: 'Enter the 6-digit code' },
            })}
          />
          {errors.token && <p className="mt-1 text-xs text-red-600">{errors.token.message}</p>}
        </div>

        {/* New password with toggle */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            New password <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <input
              type={showPw ? 'text' : 'password'}
              placeholder="At least 8 characters"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
              {...register('password', {
                required: 'Password is required',
                minLength: { value: 8, message: 'At least 8 characters' },
              })}
            />
            <button
              type="button"
              onClick={() => setShowPw((v) => !v)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              {showPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
            </button>
          </div>
          {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>}
        </div>

        <Input
          label="Confirm new password"
          type="password"
          placeholder="••••••••"
          required
          error={errors.password_confirmation?.message}
          {...register('password_confirmation', {
            required: 'Please confirm your password',
            validate:  (v) => v === watch('password') || 'Passwords do not match',
          })}
        />

        {error && (
          <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{error}</p>
        )}

        <Button type="submit" className="w-full" size="lg" loading={loading}>
          Reset password
        </Button>
      </form>

      <div className="mt-6 flex items-center justify-between text-sm text-gray-400">
        <Link href="/login" className="inline-flex items-center gap-1.5 hover:text-brand-500 transition-colors">
          <ArrowLeft className="w-3.5 h-3.5" />
          Back to sign in
        </Link>
        <Link href="/forgot-password" className="hover:text-brand-500 transition-colors">
          Resend code
        </Link>
      </div>
    </>
  );
}

// Page wrapper with Suspense boundary (required for useSearchParams in Next.js App Router)
export default function ResetPasswordPage() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 mb-4">
            <span className="text-3xl">&#127979;</span>
          </div>
          <h1 className="text-2xl font-bold text-white">StudentsHub</h1>
          <p className="text-blue-200 text-sm mt-1">Daffodil International University</p>
        </div>
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <Suspense fallback={<div className="text-center text-sm text-gray-400 py-8">Loading...</div>}>
            <ResetPasswordForm />
          </Suspense>
        </div>
      </div>
    </div>
  );
}
