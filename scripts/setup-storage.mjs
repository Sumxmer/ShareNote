import { createClient } from '@supabase/supabase-js';

if (process.env.NODE_ENV !== 'production') process.loadEnvFile('.env.local');
const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
const key = process.env.SUPABASE_SECRET_KEY || process.env.SUPABASE_SERVICE_ROLE_KEY;
if (!url || !key) throw new Error('Missing Supabase server configuration');
const db = createClient(url, key, { auth: { persistSession: false, autoRefreshToken: false } });
const bucket = 'note-upload-staging';
const options = { public: false, fileSizeLimit: 10485760, allowedMimeTypes: ['application/octet-stream'] };
const listed = await db.storage.listBuckets();
if (listed.error) throw new Error(`Cannot inspect storage (${listed.error.statusCode || 'unknown'})`);
const existing = listed.data.find(value => value.id === bucket);
if (existing?.public) throw new Error('Staging bucket must be private; inspect it before proceeding');
const result = existing ? await db.storage.updateBucket(bucket, options) : await db.storage.createBucket(bucket, options);
if (result.error) throw new Error(`Cannot configure staging storage (${result.error.statusCode || 'unknown'})`);
console.log('Private staging bucket ready: 10 MiB, scoped uploads only.');
