import type { NextConfig } from 'next';

const config: NextConfig = {
  poweredByHeader: false,
  async headers() {
    return [{ source: '/:path*', headers: [
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      { key: 'X-Frame-Options', value: 'DENY' },
      { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
      { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
    ] }];
  },
  async redirects() {
    return [
      ['index.php', '/'], ['login.php', '/login'], ['register.php', '/register'],
      ['dashboard.php', '/dashboard'], ['upload.php', '/upload'],
      ['admin/index.php', '/admin'], ['admin/manage_users.php', '/admin/users'],
      ['admin/manage_notes.php', '/admin/notes'], ['admin/security_logs.php', '/admin/logs'],
    ].map(([old, destination]) => ({ source: `/${old}`, destination, permanent: false }));
  },
};
export default config;
