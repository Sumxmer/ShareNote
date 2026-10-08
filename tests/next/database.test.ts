import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { PGlite } from '@electric-sql/pglite';
import { readFile } from 'node:fs/promises';

const owner = '11111111-1111-4111-8111-111111111111';
const other = '22222222-2222-4222-8222-222222222222';
const admin = '33333333-3333-4333-8333-333333333333';
const suspended = '44444444-4444-4444-8444-444444444444';
let db: PGlite;

async function asRole<T>(role: 'anon' | 'authenticated' | 'service_role', uid: string | null, callback: () => Promise<T>) {
  await db.exec('begin');
  try {
    await db.query("select set_config('request.jwt.claim.sub',$1,true)",[uid || '']);
    await db.exec(`set local role ${role}`);
    return await callback();
  } finally { await db.exec('rollback'); }
}

beforeAll(async () => {
  db = new PGlite();
  // Emulate only the platform-owned schemas; execute the actual app migration unchanged.
  await db.exec(`
    create role anon; create role authenticated; create role service_role bypassrls;
    create schema auth; create schema storage;
    create table auth.users(id uuid primary key,email text,raw_user_meta_data jsonb default '{}'::jsonb);
    create function auth.uid() returns uuid language sql stable as $$ select nullif(current_setting('request.jwt.claim.sub',true),'')::uuid $$;
    grant usage on schema auth to anon,authenticated,service_role;
    grant execute on function auth.uid() to anon,authenticated,service_role;
    create table storage.buckets(id text primary key,name text,public boolean,file_size_limit bigint,allowed_mime_types text[]);
    create table storage.objects(id integer generated always as identity primary key,bucket_id text,name text,metadata jsonb);
    alter table storage.objects enable row level security;
    grant usage on schema storage to anon,authenticated,service_role;
    grant all on storage.objects to anon,authenticated,service_role;
  `);
  await db.exec(await readFile(new URL('../../supabase/migrations/202610080001_noteshare.sql',import.meta.url),'utf8'));
  for (const [id,name] of [[owner,'owner_user'],[other,'other_user'],[admin,'admin_user'],[suspended,'suspended_user']]) {
    await db.query('insert into auth.users(id,email,raw_user_meta_data) values($1,$2,$3)',[id,`${name}@example.com`,JSON.stringify({ username: name,full_name: 'นักศึกษาทดสอบ',role: 'admin',status: 'active' })]);
  }
  await db.query("update public.profiles set role='admin' where user_id=$1",[admin]);
  await db.query("update public.profiles set status='suspended' where user_id=$1",[suspended]);
  await db.query("insert into storage.objects(bucket_id,name,metadata) values('notes',$1,$2)",[`${owner}/valid.pdf`,JSON.stringify({ size: 50,mimetype: 'application/pdf' })]);
  await db.query("insert into storage.objects(bucket_id,name,metadata) values('note-upload-staging',$1,$2)",[`${owner}/unchecked.pdf`,JSON.stringify({ size: 50,mimetype: 'application/pdf' })]);
  await db.query(`insert into public.notes(user_id,subject_id,title,description,object_path,original_file_name,file_size,file_type,mime_type)
    values($1,1,'SQL ชีททดสอบ','สรุปภาษาไทย',$2,'ชีทเรียน.pdf',50,'pdf','application/pdf')`,[owner,`${owner}/seed.pdf`]);
  await db.query(`insert into public.notes(user_id,subject_id,title,object_path,original_file_name,file_size,file_type,mime_type,status)
    values($1,1,'Hidden note',$2,'hidden.pdf',50,'pdf','application/pdf','removed')`,[owner,`${owner}/hidden.pdf`]);
  await db.query("insert into public.comments(note_id,user_id,content) values(1,$1,'ความคิดเห็นทดสอบ')",[other]);
},60000);
afterAll(async () => { await db?.close(); });

describe('Supabase migration and least privilege', () => {
  it('creates six application tables and a private 10 MiB bucket', async () => {
    const tables = await db.query<{ count: number }>("select count(*)::int as count from information_schema.tables where table_schema='public' and table_type='BASE TABLE'");
    expect(tables.rows[0].count).toBe(6);
    const bucket = await db.query<{ public: boolean; file_size_limit: number }>('select public,file_size_limit from storage.buckets');
    expect(bucket.rows[0].public).toBe(false); expect(Number(bucket.rows[0].file_size_limit)).toBe(10485760);
  });
  it('ignores role supplied in signup metadata', async () => {
    const row = await db.query<{ role: string }>('select role from public.profiles where user_id=$1',[owner]);
    expect(row.rows[0].role).toBe('user');
  });
  it('lets guests read active notes but not removed notes', async () => {
    const result = await asRole('anon',null,() => db.query<{ title: string }>('select title from public.note_catalog'));
    expect(result.rows.map(r => r.title)).toEqual(['SQL ชีททดสอบ']);
  });
  it('does not expose email addresses to guests', async () => {
    await expect(asRole('anon',null,() => db.query('select email from public.profiles'))).rejects.toThrow(/permission denied/i);
  });
  it('does not expose email addresses to ordinary members', async () => {
    await expect(asRole('authenticated',owner,() => db.query('select email from public.profiles'))).rejects.toThrow(/permission denied/i);
  });
  it('prevents members from changing their role directly', async () => {
    await expect(asRole('authenticated',owner,() => db.query("update public.profiles set role='admin' where user_id=$1",[owner]))).rejects.toThrow(/permission denied/i);
  });
  it('prevents admins from changing roles through direct table writes', async () => {
    await expect(asRole('authenticated',admin,() => db.query("update public.profiles set role='admin' where user_id=$1",[other]))).rejects.toThrow(/permission denied/i);
  });
  it('prevents direct note writes that bypass RPC validation', async () => {
    await expect(asRole('authenticated',owner,() => db.query("update public.notes set download_count=999 where note_id=1"))).rejects.toThrow(/permission denied/i);
  });
  it('keeps security logs hidden from members', async () => {
    const result = await asRole('authenticated',owner,() => db.query('select * from public.security_logs'));
    expect(result.rows).toHaveLength(0);
  });
  it('does not allow clients to forge or delete security logs', async () => {
    await expect(asRole('authenticated',owner,() => db.query("insert into public.security_logs(action) values('FORGED')"))).rejects.toThrow(/permission denied/i);
    await expect(asRole('authenticated',admin,() => db.query('delete from public.security_logs'))).rejects.toThrow(/permission denied/i);
  });
  it('does not let members call the private audit helper', async () => {
    await expect(asRole('authenticated',owner,() => db.query("select private.audit('FORGED','test')"))).rejects.toThrow(/permission denied/i);
  });
  it('keeps storage objects inaccessible to public and user keys', async () => {
    for (const role of ['anon','authenticated'] as const) {
      const result = await asRole(role,role==='authenticated' ? owner : null,() => db.query("select * from storage.objects where bucket_id='notes'"));
      expect(result.rows).toHaveLength(0);
    }
  });
});

describe('ownership, transactions and revocation', () => {
  it('creates a note and audit events in the same transaction', async () => {
    await asRole('authenticated',owner,async () => {
      const result = await db.query<{ id: number }>("select public.create_note('ชีทใหม่','','SQL',$1,'ใหม่.pdf',50,'pdf','application/pdf') as id",[`${owner}/valid.pdf`]);
      expect(result.rows[0].id).toBeGreaterThan(2);
      // Switch to the test owner to inspect the audit transaction without weakening app grants.
      await db.exec('reset role');
      const logs = await db.query<{ action: string }>("select action from public.security_logs where detail=$1 order by log_id",[`note_id=${result.rows[0].id}`]);
      expect(logs.rows.map(l => l.action)).toEqual(['NOTE_CREATE','FILE_UPLOAD']);
    });
  });
  it('rejects uploads referencing another account or a nonexistent object', async () => {
    await expect(asRole('authenticated',other,() => db.query("select public.create_note('forged','','SQL',$1,'f.pdf',50,'pdf','application/pdf')",[`${owner}/valid.pdf`]))).rejects.toThrow(/ownership/i);
    await expect(asRole('authenticated',owner,() => db.query("select public.create_note('forged','','SQL',$1,'f.pdf',50,'pdf','application/pdf')",[`${owner}/missing.pdf`]))).rejects.toThrow(/ownership/i);
  });
  it('rejects forged file MIME and size even for an owned storage object', async () => {
    await expect(asRole('authenticated',owner,() => db.query("select public.create_note('forged','','SQL',$1,'f.pdf',50,'pdf','text/html')",[`${owner}/valid.pdf`]))).rejects.toThrow(/ownership/i);
    await expect(asRole('authenticated',owner,() => db.query("select public.create_note('forged','','SQL',$1,'f.pdf',999,'pdf','application/pdf')",[`${owner}/valid.pdf`]))).rejects.toThrow(/ownership/i);
  });
  it('cannot publish an unchecked staged object through the public RPC', async () => {
    await expect(asRole('authenticated',owner,() => db.query("select public.create_note('unchecked','','SQL',$1,'f.pdf',50,'pdf','application/pdf')",[`${owner}/unchecked.pdf`]))).rejects.toThrow(/ownership/i);
  });
  it('allows owners to edit their own notes', async () => {
    await asRole('authenticated',owner,async () => {
      await db.query("select public.update_note(1,'Updated','แก้ไข','SQL')");
      const note = await db.query<{ title: string }>('select title from public.notes where note_id=1');
      expect(note.rows[0].title).toBe('Updated');
    });
  });
  it('rejects editing and removing another member\'s note', async () => {
    await expect(asRole('authenticated',other,() => db.query("select public.update_note(1,'forged','','SQL')"))).rejects.toThrow(/forbidden/i);
    await expect(asRole('authenticated',other,() => db.query('select public.remove_note(1)'))).rejects.toThrow(/forbidden/i);
  });
  it('allows admins to manage other members\' notes', async () => {
    await asRole('authenticated',admin,async () => {
      await db.query("select public.update_note(1,'Moderated','','SQL')");
      await db.query('select public.remove_note(1)');
      const row = await db.query<{ status: string }>('select status from public.notes where note_id=1');
      expect(row.rows[0].status).toBe('removed');
    });
  });
  it('soft-deletes notes and prevents later downloads or comments', async () => {
    await asRole('authenticated',owner,async () => {
      await db.query('select public.remove_note(1)');
      const row = await db.query<{ status: string }>('select status from public.notes where note_id=1');
      expect(row.rows[0].status).toBe('removed');
    });
    await expect(asRole('authenticated',other,() => db.query('select public.record_download(2)'))).rejects.toThrow(/not found/i);
    await expect(asRole('authenticated',other,() => db.query("select public.add_comment(2,'hidden')"))).rejects.toThrow(/not found/i);
  });
  it('requires ownership to delete a comment', async () => {
    await expect(asRole('authenticated',owner,() => db.query('select public.remove_comment(1)'))).rejects.toThrow(/forbidden/i);
    await asRole('authenticated',other,async () => {
      await db.query('select public.remove_comment(1)');
      expect((await db.query('select * from public.comments where comment_id=1')).rows).toHaveLength(0);
    });
  });
  it('updates the download counter, history and audit together', async () => {
    await asRole('authenticated',owner,async () => {
      await db.query('select public.record_download(1)');
      expect((await db.query<{ download_count: number }>('select download_count from public.notes where note_id=1')).rows[0].download_count).toBe(1);
      expect((await db.query('select * from public.download_logs')).rows).toHaveLength(1);
      await db.exec('reset role');
      expect((await db.query("select * from public.security_logs where action='FILE_DOWNLOAD'")).rows).toHaveLength(1);
    });
  });
  it('blocks suspended users even with an existing JWT', async () => {
    await expect(asRole('authenticated',suspended,() => db.query('select public.record_download(1)'))).rejects.toThrow(/authentication required/i);
  });
  it('restricts admin operations and prevents self-suspension', async () => {
    await expect(asRole('authenticated',owner,() => db.query('select * from public.admin_users()'))).rejects.toThrow(/forbidden/i);
    await expect(asRole('authenticated',admin,() => db.query("select public.set_user_status($1,'suspended')",[admin]))).rejects.toThrow(/forbidden/i);
    await asRole('authenticated',admin,async () => {
      await db.query("select public.set_user_status($1,'suspended')",[owner]);
      const status = await db.query<{ status: string }>('select status from public.profiles where user_id=$1',[owner]);
      expect(status.rows[0].status).toBe('suspended');
    });
  });
  it('treats SQL injection search payloads as data', async () => {
    const result = await asRole('anon',null,() => db.query('select * from public.search_notes($1,null,1)',["' OR '1'='1"]));
    expect(result.rows).toHaveLength(0);
  });
  it('accepts Thai search text', async () => {
    const result = await asRole('anon',null,() => db.query("select * from public.search_notes('ชีท',null,1)"));
    expect(result.rows).toHaveLength(1);
  });
  it('checks login failures in a rolling 15-minute window', async () => {
    await asRole('service_role',null,async () => {
      for (let i=0;i<5;i++) await db.query("select public.record_auth_event('LOGIN_FAILED','blocked@example.com',null,null)");
      const locked = await db.query<{ locked: boolean }>("select public.login_is_locked('blocked@example.com',null) as locked");
      expect(locked.rows[0].locked).toBe(true);
      await db.query("update public.security_logs set created_at=now()-interval '16 minutes' where action='LOGIN_FAILED'");
      expect((await db.query<{ locked: boolean }>("select public.login_is_locked('blocked@example.com',null) as locked")).rows[0].locked).toBe(false);
    });
  });
  it('prevents ordinary users from calling service-only authentication logging', async () => {
    await expect(asRole('authenticated',owner,() => db.query("select public.record_auth_event('LOGIN_SUCCESS','forged@example.com',null,null)"))).rejects.toThrow(/permission denied/i);
  });
});
