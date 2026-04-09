import { redirect } from 'next/navigation';

// Root "/" → redirect based on auth (handled by middleware)
export default function Home() {
  redirect('/login');
}
