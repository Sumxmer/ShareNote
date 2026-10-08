-- Incremental update for an existing NoteShare database. Run the whole file.
-- Existing published notes enter the approval queue once; files and accounts are retained.
begin;

do $$
begin
  if exists (select 1 from pg_constraint where conrelid='public.notes'::regclass
    and conname='notes_status_check' and position('pending' in pg_get_constraintdef(oid))=0) then
    alter table public.notes drop constraint notes_status_check;
    alter table public.notes add constraint notes_status_check check (status in ('pending','active','rejected','removed'));
    update public.notes set status='pending',updated_at=now() where status='active';
  end if;
end; $$;
alter table public.notes alter column status set default 'pending';
create index if not exists notes_pending_review_idx on public.notes(created_at desc,note_id desc) where status='pending';

create or replace function public.create_note(p_title text, p_description text, p_subject text, p_object_path text,
  p_original_name text, p_size integer, p_type text, p_mime text)
returns integer language plpgsql security definer set search_path = '' as $$
declare actor uuid; sid integer; nid integer;
begin
  actor := private.require_active();
  if char_length(trim(p_title)) not between 1 and 200 or char_length(p_description) > 2000
     or char_length(trim(p_subject)) not between 1 and 150 then raise exception 'Invalid note'; end if;
  if split_part(p_object_path,'/',1) <> actor::text
     or right(p_object_path,char_length(p_type)+1) <> '.' || p_type
     or not exists(select 1 from storage.objects where bucket_id = 'notes' and name = p_object_path
       and metadata->>'size' = p_size::text and metadata->>'mimetype' = p_mime)
     then raise exception 'Invalid file ownership' using errcode = '42501'; end if;
  insert into public.subjects(subject_name) values(trim(p_subject)) on conflict(subject_name) do nothing;
  select subject_id into sid from public.subjects where subject_name = trim(p_subject);
  insert into public.notes(user_id,subject_id,title,description,object_path,original_file_name,file_size,file_type,mime_type,status)
    values(actor,sid,trim(p_title),p_description,p_object_path,p_original_name,p_size,p_type,p_mime,'pending') returning note_id into nid;
  perform private.audit('NOTE_CREATE','note_id=' || nid);
  perform private.audit('FILE_UPLOAD','note_id=' || nid);
  return nid;
end; $$;

create or replace function public.update_note(p_note_id integer, p_title text, p_description text, p_subject text)
returns void language plpgsql security definer set search_path = '' as $$
declare actor uuid; owner_id uuid; sid integer;
begin
  actor := private.require_active();
  select user_id into owner_id from public.notes where note_id=p_note_id and status in ('active','pending','rejected') for update;
  if not found then raise exception 'Note not found' using errcode='P0002'; end if;
  if owner_id <> actor and not private.is_admin() then raise exception 'Forbidden' using errcode='42501'; end if;
  if char_length(trim(p_title)) not between 1 and 200 or char_length(p_description)>2000
     or char_length(trim(p_subject)) not between 1 and 150 then raise exception 'Invalid note'; end if;
  insert into public.subjects(subject_name) values(trim(p_subject)) on conflict(subject_name) do nothing;
  select subject_id into sid from public.subjects where subject_name=trim(p_subject);
  update public.notes set title=trim(p_title),description=p_description,subject_id=sid,status='pending',updated_at=now() where note_id=p_note_id;
  perform private.audit('NOTE_UPDATE','note_id=' || p_note_id);
end; $$;

create or replace function public.remove_note(p_note_id integer) returns void language plpgsql security definer set search_path = '' as $$
declare actor uuid; owner_id uuid;
begin
  actor := private.require_active();
  select user_id into owner_id from public.notes where note_id=p_note_id and status in ('active','pending','rejected') for update;
  if not found then raise exception 'Note not found' using errcode='P0002'; end if;
  if owner_id <> actor and not private.is_admin() then raise exception 'Forbidden' using errcode='42501'; end if;
  update public.notes set status='removed',updated_at=now() where note_id=p_note_id;
  perform private.audit('NOTE_DELETE','note_id=' || p_note_id);
end; $$;

-- RLS already restricts unpublished notes to their owner and active administrators.
-- Only the administrator can transition a pending note to published/rejected.
create or replace function public.review_note(p_note_id integer,p_decision text)
returns void language plpgsql security definer set search_path = '' as $$
declare current_status text;
begin
  if not private.is_admin() then raise exception 'Forbidden' using errcode='42501'; end if;
  if p_decision is null or p_decision not in ('active','rejected') then raise exception 'Invalid decision'; end if;
  select status into current_status from public.notes where note_id=p_note_id for update;
  if not found or current_status <> 'pending' then raise exception 'Note is not pending' using errcode='P0002'; end if;
  update public.notes set status=p_decision,updated_at=now() where note_id=p_note_id;
  perform private.audit(case when p_decision='active' then 'NOTE_APPROVE' else 'NOTE_REJECT' end,'note_id=' || p_note_id);
end; $$;
revoke all on function public.review_note(integer,text) from public,anon,authenticated;
grant execute on function public.review_note(integer,text) to authenticated;

-- Authorize again after reading Storage so removal/rejection cannot race the response.
-- Administrator review downloads are audited without increasing public counters.
create or replace function public.record_download(p_note_id integer)
returns void language plpgsql security definer set search_path = '' as $$
declare actor uuid; current_status text;
begin
  actor := private.require_active();
  select status into current_status from public.notes where note_id=p_note_id for update;
  if not found then raise exception 'Note not found' using errcode='P0002'; end if;
  if current_status='active' then
    update public.notes set download_count=download_count+1 where note_id=p_note_id;
    insert into public.download_logs(note_id,user_id) values(p_note_id,actor);
    perform private.audit('FILE_DOWNLOAD','note_id=' || p_note_id);
  elsif current_status in ('pending','rejected') and private.is_admin() then
    perform private.audit('ADMIN_NOTE_REVIEW','note_id=' || p_note_id);
  else
    raise exception 'Note not found' using errcode='P0002';
  end if;
end; $$;

create or replace function public.my_stats() returns jsonb language plpgsql stable security definer set search_path = '' as $$
declare actor uuid;
begin
  actor := private.require_active();
  return jsonb_build_object(
    'total',(select count(*) from public.notes where user_id=actor),
    'active',(select count(*) from public.notes where user_id=actor and status='active'),
    'pending',(select count(*) from public.notes where user_id=actor and status='pending'),
    'rejected',(select count(*) from public.notes where user_id=actor and status='rejected'),
    'downloads',(select coalesce(sum(download_count),0) from public.notes where user_id=actor),
    'comments',(select count(*) from public.comments c join public.notes n using(note_id) where n.user_id=actor));
end; $$;

create or replace function public.admin_stats() returns jsonb language plpgsql stable security definer set search_path = '' as $$
begin
  if not private.is_admin() then raise exception 'Forbidden' using errcode='42501'; end if;
  return jsonb_build_object('users',(select count(*) from public.profiles),
    'notes',(select count(*) from public.notes where status='active'),
    'pending',(select count(*) from public.notes where status='pending'),
    'comments',(select count(*) from public.comments),'downloads',(select count(*) from public.download_logs));
end; $$;

notify pgrst, 'reload schema';
commit;
