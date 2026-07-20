---
title: Storage and File Uploads
slug: storage-and-file-uploads
page: storage-and-file-uploads.html
section: Settings
license: regular
keywords: [storage, s3, r2, cloudflare, spaces, digitalocean, wasabi, backblaze, b2, uploads, media, bucket, cdn, migration]
---

Every file your site stores — logos, avatars, blog images, generated AI images — lives on
one **media disk**. Out of the box that disk is the server MakeAI runs on. You can switch it
to any S3-compatible bucket at any time from **Settings → Storage**, without touching a
config file: credentials are entered in the admin panel and stored encrypted in the database.

Supported providers: **Amazon S3**, **Cloudflare R2**, **DigitalOcean Spaces**, **Wasabi**,
and **Backblaze B2**.

> Nothing about cloud storage is configured in `.env`. The `AWS_*` keys in `.env.example` are
> for the optional SQS queue driver only — they do not affect media storage.

## Local storage (the default)

Files are written inside your webroot and served directly. This is set up for you during
installation and needs no symlink (`storage:link`), which matters on shared hosting where
symlinks are often blocked.

Local storage is a perfectly good choice. Move to a bucket when you want to:

- serve media from a CDN,
- keep uploads when you redeploy or move servers (or run more than one app server),
- stop worrying about disk space.

## Switching to a cloud bucket

1. Create a bucket at your provider and generate an access key + secret with read/write
   permission on it.
2. Make the bucket's objects **publicly readable** (see the per-provider notes below) —
   MakeAI serves images straight from it, so a private bucket shows broken images.
3. Go to **Settings → Storage**, pick the provider, and enter the credentials.
4. Press **Test Connection**. This performs a real write → read → delete against the bucket
   and reports the provider's own error if anything is wrong. Nothing is saved or activated
   by a test.
5. Press **Save**.

### What happens when you save

If you already have uploaded files, MakeAI **copies them to the new bucket first and only
switches over once every file has arrived.** Your site keeps serving from its current
location for the whole copy, so visitors never see broken images mid-migration.

Two things to know:

- **A queue worker must be running**, or the copy never starts. See *Setting up cron* and
  make sure your worker is up. The Storage screen shows live progress and will tell you if
  the migration has stalled; you can clear it and retry.
- **Files are copied, never moved.** The originals stay where they are, so the switch is
  safe and re-runnable — and switching back to local storage is instant, because the local
  copies were never deleted.

The copy is resumable: re-running it skips any file already in the bucket, so an interrupted
migration picks up where it left off rather than starting over.

## Per-provider notes

**Amazon S3 / DigitalOcean Spaces / Wasabi** — these support per-object ACLs, so MakeAI marks
each uploaded file public automatically. Just make sure the bucket policy doesn't block public
access.

**Cloudflare R2 / Backblaze B2** — these do **not** support S3 object ACLs. Public access is
granted at the *bucket* level instead, and you must give MakeAI the public URL to serve from:

- **R2**: enable a public development URL or connect a custom domain, then paste it into the
  **Public URL** field.
- **B2**: set the bucket to *Public*, then paste its public/CDN URL into **Public URL**.

The **Public URL** field is required for R2 and B2 for exactly this reason — without it,
uploaded files have no address a browser can reach.

**Endpoint** — required for R2, Spaces, Wasabi and B2 (each provider gives you one). Leave it
blank for Amazon S3.

## Moving between providers

Use **Migrate Existing Files** on the same screen to copy files from one location to another
without changing the active driver. This is useful for switching providers, or for pre-seeding
a bucket before you cut over.

## Troubleshooting

**Images are broken after switching to a bucket.** The bucket isn't publicly readable, or (on
R2/B2) the Public URL is missing or wrong. Re-check step 2 above. Test Connection only proves
MakeAI can *write* to the bucket — it cannot detect that the public *read* path is misconfigured.

**The migration sits at 0 and never moves.** No queue worker is running. Start one, then clear
and restart the migration.

**Uploads fail with a storage error.** The credentials no longer have write permission, or the
bucket was deleted. MakeAI now reports the failure instead of silently saving a broken image —
run Test Connection to see the provider's exact error.

**Some old images still 404 after migrating.** Run **Migrate Existing Files** again; it will
copy anything that was added after the first pass and skip everything already there.
