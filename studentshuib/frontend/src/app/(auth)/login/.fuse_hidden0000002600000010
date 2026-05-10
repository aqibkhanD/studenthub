'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { authApi } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';
import { Button, Input, useToast } from '@/components/ui';
import type { User } from '@/types';
import Link from 'next/link';
import { Eye, EyeOff } from 'lucide-react';

interface LoginForm { email: string; password: string; }

export default function LoginPage() {
  const router   = useRouter();
  const setAuth  = useAuthStore((s) => s.setAuth);
  const { toast }= useToast();
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const { register, handleSubmit, formState: { errors } } = useForm<LoginForm>();

  const onSubmit = async (data: LoginForm) => {
    setLoading(true);
    try {
      const res   = await authApi.login(data.email, data.password);
      const { token, user } = res.data as { token: string; user: User };
      setAuth(user, token);
      // Route by role. Student URLs do NOT have /student/ prefix
      // (route group `(student)` is invisible).
      if (['admin', 'dept_head', 'super_admin', 'management'].includes(user.role)) {
        router.push('/admin/dashboard');
      } else {
        router.push('/dashboard');
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast({ title: msg || 'Login failed. Please check your credentials.', variant: 'error' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4">
      <div className="w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 mb-4">
            <span className="text-3xl">&#127979;</span>
          </div>
          <h1 className="text-2xl font-bold text-white">StudentsHub</h1>
          <p className="text-blue-200 text-sm mt-1">Daffodil International University</p>
        </div>

        {/* Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <h2 className="text-xl font-semibold text-gray-900 mb-6">Sign in to your account</h2>

          <form
            onSubmit={handleSubmit(onSubmit)}
            // Explicit Enter handler — some autofill plugins / hydration timing
            // edge cases swallow the browser's implicit form submission on Enter.
            // Forcing it here guarantees the form submits regardless.
            onKeyDown={(e) => {
              if (e.key !== 'Enter' || e.shiftKey || e.repeat) return;
              const tag = (e.target as HTMLElement).tagName;
              if (tag === 'BUTTON' || tag === 'TEXTAREA' || tag === 'A') return;
              e.preventDefault();
              handleSubmit(onSubmit)();
            }}
            className="space-y-4"
          >
            <Input
              label="Email address"
              type="email"
              placeholder="you@diu.edu.bd"
              required
              autoComplete="email"
              error={errors.email?.message}
              {...register('email', { required: 'Email is required' })}
            />

            {/* Password field with visibility toggle */}
            <div>
              <div className="relative">
                <Input
                  label="Password"
                  type={showPassword ? 'text' : 'password'}
                  placeholder="••••••••"
                  required
                  autoComplete="current-password"
                  className="pr-10"
                  error={errors.password?.message}
                  {...register('password', { required: 'Password is required' })}
                />
                {/* Eye toggle — sits over the input. Top offset accounts for the label height. */}
                <button
                  type="button"
                  onClick={() => setShowPassword(v => !v)}
                  className="absolute right-3 top-[33px] text-gray-400 hover:text-gray-600"
                  tabIndex={-1}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              <div className="mt-1.5 text-right">
                <Link href="/forgot-password" className="text-xs text-brand-500 hover:underline">
                  Forgot password?
                </Link>
              </div>
            </div>

            <Button type="submit" className="w-full" size="lg" loading={loading}>
              Sign in
            </Button>
          </form>

          <p className="mt-6 text-center text-sm text-gray-500">
            New student?{' '}
            <Link href="/register" className="text-brand-500 font-medium hover:underline">
              Register here
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
