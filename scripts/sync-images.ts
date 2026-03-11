/// <reference types="bun-types" />
import { PutObjectCommand, ListObjectsV2Command } from '@aws-sdk/client-s3';
import { createS3Client, S3_BUCKET } from '../src/lib/s3';
import { lookup } from 'mime-types';
import { readdir, stat, readFile } from 'node:fs/promises';
import { join, relative } from 'node:path';

const DRY_RUN = process.argv.includes('--dry-run');
const IMAGES_DIR = join(process.cwd(), 'images');

async function walkDir(dir: string): Promise<string[]> {
  const entries = await readdir(dir, { withFileTypes: true });
  const files: string[] = [];
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await walkDir(fullPath));
    } else if (entry.name !== '.gitkeep') {
      files.push(fullPath);
    }
  }
  return files;
}

async function getRemoteObjects(client: ReturnType<typeof createS3Client>) {
  const remote = new Map<string, number>();
  let continuationToken: string | undefined;

  do {
    const response = await client.send(new ListObjectsV2Command({
      Bucket: S3_BUCKET,
      Prefix: 'images/',
      ContinuationToken: continuationToken,
    }));

    for (const obj of response.Contents ?? []) {
      if (obj.Key && obj.Size != null) {
        remote.set(obj.Key, obj.Size);
      }
    }
    continuationToken = response.NextContinuationToken;
  } while (continuationToken);

  return remote;
}

async function main() {
  if (!process.env.S3_ACCESS_KEY_ID || !process.env.S3_SECRET_ACCESS_KEY) {
    console.error('Missing S3 credentials. Set S3_ACCESS_KEY_ID and S3_SECRET_ACCESS_KEY.');
    process.exit(1);
  }

  if (!S3_BUCKET) {
    console.error('Missing S3_BUCKET environment variable.');
    process.exit(1);
  }

  const client = createS3Client();

  let localFiles: string[];
  try {
    localFiles = await walkDir(IMAGES_DIR);
  } catch {
    console.log('No images/ directory found. Nothing to sync.');
    process.exit(0);
  }

  if (localFiles.length === 0) {
    console.log('No images to sync.');
    process.exit(0);
  }

  const remote = await getRemoteObjects(client);

  let uploaded = 0;
  let skipped = 0;
  let failed = 0;

  for (const filePath of localFiles) {
    const key = `images/${relative(IMAGES_DIR, filePath)}`;
    const fileStat = await stat(filePath);
    const remoteSize = remote.get(key);

    if (remoteSize != null && remoteSize === fileStat.size) {
      skipped++;
      continue;
    }

    const contentType = lookup(filePath) || 'application/octet-stream';

    if (DRY_RUN) {
      console.log(`[dry-run] Would upload: ${key} (${fileStat.size} bytes, ${contentType})`);
      uploaded++;
      continue;
    }

    try {
      const body = await readFile(filePath);
      await client.send(new PutObjectCommand({
        Bucket: S3_BUCKET,
        Key: key,
        Body: body,
        ContentType: contentType,
      }));
      console.log(`Uploaded: ${key} (${fileStat.size} bytes)`);
      uploaded++;
    } catch (err) {
      console.error(`Failed to upload ${key}:`, err);
      failed++;
    }
  }

  console.log(`\nSync complete: ${uploaded} uploaded, ${skipped} skipped, ${failed} failed, ${localFiles.length} total`);
  if (failed > 0) {
    process.exit(1);
  }
}

main().catch((err) => {
  console.error('Sync failed:', err);
  process.exit(1);
});
