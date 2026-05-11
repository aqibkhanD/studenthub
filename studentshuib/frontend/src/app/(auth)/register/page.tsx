'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { authApi } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';
import { Button, Input, useToast } from '@/components/ui';
import type { User } from '@/types';
import Link from 'next/link';

interface RegisterForm {
  student_id: string; name: string; email: string;
  phone: string; password: string; password_confirmation: string;
  program: string; batch: string;
}

export default function RegisterPage() {
  const router    = useRouter();
  const setAuth   = useAuthStore((s) => s.setAuth);
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);

  const { register, handleSubmit, watch, formState: { errors } } = useForm<RegisterForm>();
  const password = watch('password');

  const onSubmit = async (data: RegisterForm) => {
    setLoading(true);
    try {
      const res = await authApi.register(data);
      const { token, user } = res.data as { token: string; user: User };
      setAuth(user, token);
      // Student URLs do NOT have a /student/ prefix (route group is invisible).
      router.push('/dashboard');
    } catch (err: unknown) {
      const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      const msg = errors ? Object.values(errors).flat()[0] : 'Registration failed. Please try again.';
      toast(msg, 'error');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4">
      <div className="w-full max-w-lg">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-white">StudentsHub</h1>
          <p className="text-blue-200 text-sm mt-1">Daffodil International University</p>
        </div>

        <div className="bg-white rounded-2xl shadow-xl p-8">
          <h2 className="text-xl font-semibold text-gray-900 mb-6">Create your account</h2>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Student ID"
                placeholder="221-15-5812"
                required
                error={errors.student_id?.message}
                {...register('student_id', { required: 'Student ID required' })}
              />
              <Input
                label="Full Name"
                placeholder="Your full name"
                required
                error={errors.name?.message}
                {...register('name', { required: 'Name required' })}
              />
            </div>
            <Input
              label="DIU Email"
              type="email"
              placeholder="you@diu.edu.bd"
              required
              error={errors.email?.message}
              {...register('email', { required: 'Email required' })}
            />
            <Input
              label="Phone Number"
              type="tel"
              placeholder="+8801712345678"
              required
              error={errors.phone?.message}
              {...register('phone', { required: 'Phone required' })}
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Program"
                placeholder="B.Sc. in CSE"
                {...register('program')}
              />
              <Input
                label="Batch"
                placeholder="55"
                {...register('batch')}
              />
            </div>
            <Input
              label="Password"
              type="password"
              placeholder="Min. 8 characters"
              required
              error={errors.password?.message}
              {...register('password', { required: 'Password required', minLength: { value: 8, message: 'Min. 8 characters' } })}
            />
            <Input
              label="Confirm Password"
              type="password"
              placeholder="Repeat password"
              required
              error={errors.password_confirmation?.message}
              {...register('password_confirmation', {
                required: 'Please confirm password',
                validate: (v) => v === password || 'Passwords do not match',
              })}
            />

            <Button type="submit" className="w-full" size="lg" loading={loading}>
              Create account
            </Button>
          </form>

          <p className="mt-6 text-center text-sm text-gray-500">
            Already have an account?{' '}
            <Link href="/login" className="text-brand-500 font-medium hover:underline">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
