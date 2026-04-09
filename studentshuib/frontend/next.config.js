/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',   // required for Docker deployment
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api/v1',
  },
  // Rewrite /api/* to Laravel backend during local dev (when not using Docker)
  async rewrites() {
    if (process.env.NODE_ENV === 'development' && !process.env.DOCKER) {
      return [
        {
          source: '/api/:path*',
          destination: `${process.env.BACKEND_URL || 'http://localhost:8000'}/api/:path*`,
        },
      ];
    }
    return [];
  },
};

module.exports = nextConfig;
