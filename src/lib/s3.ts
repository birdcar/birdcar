import { S3Client } from '@aws-sdk/client-s3';

export function createS3Client() {
  return new S3Client({
    region: process.env.S3_REGION ?? 'auto',
    endpoint: process.env.S3_ENDPOINT,
    credentials: {
      accessKeyId: process.env.S3_ACCESS_KEY_ID!,
      secretAccessKey: process.env.S3_SECRET_ACCESS_KEY!,
    },
    forcePathStyle: !!process.env.S3_FORCE_PATH_STYLE,
  });
}

export const S3_BUCKET = process.env.S3_BUCKET!;
export const CDN_BASE_URL = process.env.CDN_BASE_URL!;
