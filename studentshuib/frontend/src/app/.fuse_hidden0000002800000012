'use client';

// global-error.tsx catches errors thrown in the root layout.
// It must render its own <html> and <body> tags since the root layout is unavailable.

import { useEffect } from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';

interface ErrorProps {
  error: Error & { digest?: string };
  reset: () => void;
}

export default function GlobalError({ error, reset }: ErrorProps) {
  useEffect(() => {
    console.error('[StudentsHub] Root layout error:', error);
  }, [error]);

  return (
    <html lang="en">
      <body style={{ margin: 0, fontFamily: 'system-ui, sans-serif', backgroundColor: '#f9fafb' }}>
        <div
          style={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem',
          }}
        >
          <div style={{ textAlign: 'center', maxWidth: '22rem' }}>
            <div
              style={{
                width: '4rem', height: '4rem', borderRadius: '9999px',
                backgroundColor: '#fee2e2', display: 'flex',
                alignItems: 'center', justifyContent: 'center', margin: '0 auto 1.5rem',
              }}
            >
              <AlertTriangle style={{ width: '2rem', height: '2rem', color: '#ef4444' }} />
            </div>

            <h1 style={{ fontSize: '1.25rem', fontWeight: 700, color: '#111827', marginBottom: '0.5rem' }}>
              Critical error
            </h1>
            <p style={{ fontSize: '0.875rem', color: '#6b7280', marginBottom: '1.5rem' }}>
              The application encountered a critical error. Please try refreshing, or contact IT support if the problem persists.
            </p>

            {process.env.NODE_ENV === 'development' && error?.message && (
              <pre
                style={{
                  textAlign: 'left', fontSize: '0.75rem', backgroundColor: '#fef2f2',
                  color: '#b91c1c', borderRadius: '0.5rem', padding: '0.75rem',
                  marginBottom: '1.5rem', overflow: 'auto', maxHeight: '8rem',
                  border: '1px solid #fecaca',
                }}
              >
                {error.message}
                {error.digest ? `\n\nDigest: ${error.digest}` : ''}
              </pre>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', alignItems: 'center' }}>
              <button
                onClick={reset}
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: '0.5rem',
                  padding: '0.625rem 1.25rem', backgroundColor: '#2563eb',
                  color: '#fff', fontSize: '0.875rem', fontWeight: 500,
                  borderRadius: '0.5rem', border: 'none', cursor: 'pointer',
                }}
              >
                <RefreshCw style={{ width: '1rem', height: '1rem' }} />
                Reload application
              </button>
              <a
                href="/login"
                style={{
                  padding: '0.625rem 1.25rem', fontSize: '0.875rem',
                  color: '#374151', textDecoration: 'none',
                }}
              >
                Back to login
              </a>
            </div>
          </div>
        </div>
      </body>
    </html>
  );
}
