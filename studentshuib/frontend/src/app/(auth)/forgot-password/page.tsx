'use client';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { authApi } from '@/lib/api';
import { Button, Input } from '@/components/ui';
import Link from 'next/link';
import { CheckCircle, ArrowLeft, Mail } from 'lucide-react';

interface ForgotForm { email: string; }

export default function ForgotPasswordPage() {
  const [sent, setSent]       = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');

  const { register, handleSubmit, getValues, formState: { errors } } = useForm<ForgotForm>();

  const onSubmit = async (data: ForgotForm) => {
    setLoading(true);
    setError('');
    try {
      await authApi.forgotPassword(data.email);
      setSent(true);
    } catch {
      setError('Something went wrong. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4">
      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 mb-4">
            <span className="text-3xl">&#127979;</span>
          </div>
          <h1 className="text-2xl font-bold text-white">StudentsHub</h1>
          <p className="text-blue-200 text-sm mt-1">Daffodil International University</p>
        </div>

        <div className="bg-white rounded-2xl shadow-xl p-8">

          {sent ? (
            /* ---- Success state ---- */
            <div className="text-center space-y-4">
              <div className="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto">
                <CheckCircle className="w-7 h-7 text-green-500" />
              </div>
              <h2 className="text-lg font-semibold text-gray-900">Check your email</h2>
              <p className="text-sm text-gray-500">
                If <span className="font-medium text-gray-700">{getValues('email')}</span> is registered, we sent a 6-digit reset code. It expires in 15 minutes.
              </p>
              <Link
                href={`/reset-password?email=${encodeURIComponent(getValues('email'))}`}
                className="inline-flex items-center justify-center w-full px-4 py-2.5 bg-brand-500 text-white text-sm font-medium rounded-lg hover:bg-brand-600 transition-colors"
              >
                Enter reset code
              </Link>
              <button
                onClick={() => setSent(false)}
                className="block w-full text-sm text-gray-400 hover:text-gray-600 mt-2"
              >
                Resend code
              </button>
            </div>
          ) : (
            /* ---- Request form ---- */
            <>
              <div className="flex items-center gap-3 mb-6">
                <div className="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center shrink-0">
                  <Mail className="w-5 h-5 text-brand-500" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-gray-900">Reset your password</h2>
                  <p className="text-xs text-gray-400 mt-0.5">We will send a 6-digit code to your email.</p>
                </div>
              </div>

              <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                <Input
                  label="Email address"
                  type="email"
                  placeholder="you@diu.edu.bd"
                  required
                  error={errors.email?.message}
                  {...register('email', {
                    required: 'Email is required',
                    pattern: { value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: 'Enter a valid email' },
                  })}
                />

                {error && (
                  <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{error}</p>
                )}

                <Button type="submit" className="w-full" size="lg" loading={loading}>
                  Send reset code
                </Button>
              </form>

              <div className="mt-6 text-center">
                <Link href="/login" className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 transition-colors">
                  <ArrowLeft className="w-3.5 h-3.5" />
                  Back to sign in
                </Link>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
