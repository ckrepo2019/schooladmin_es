import { S3Client, ListObjectsV2Command } from '@aws-sdk/client-s3';
import dotenv from 'dotenv';

dotenv.config();

// Configure S3 Client for Vultr Object Storage
const s3Client = new S3Client({
  endpoint: process.env.VULTR_ENDPOINT,
  region: process.env.VULTR_REGION,
  credentials: {
    accessKeyId: process.env.VULTR_ACCESS_KEY,
    secretAccessKey: process.env.VULTR_SECRET_KEY,
  },
  forcePathStyle: true,
});

// Helper function to format bytes to human-readable format
function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Helper function to extract folder from S3 key
function extractFolder(key) {
  const parts = key.split('/');
  return parts.length > 1 ? parts[0] : 'root';
}

// Get all files in the bucket
export const getAllBucketFiles = async (req, res) => {
  try {
    const allObjects = [];
    let continuationToken = undefined;

    // Handle pagination for buckets with >1000 objects
    do {
      const command = new ListObjectsV2Command({
        Bucket: process.env.VULTR_BUCKET_NAME,
        ContinuationToken: continuationToken,
      });

      const response = await s3Client.send(command);

      if (response.Contents) {
        allObjects.push(...response.Contents);
      }

      continuationToken = response.NextContinuationToken;
    } while (continuationToken);

    // Transform S3 objects to frontend-friendly format
    const files = allObjects.map((obj) => {
      const folder = extractFolder(obj.Key);
      const filename = obj.Key.split('/').pop();
      const url = `${process.env.VULTR_ENDPOINT.replace('https://', 'https://' + process.env.VULTR_BUCKET_NAME + '.')}/${obj.Key}`;

      return {
        key: obj.Key,
        filename: filename,
        folder: folder,
        size: obj.Size || 0,
        lastModified: obj.LastModified || new Date(),
        url: url,
        etag: obj.ETag || '',
      };
    });

    res.status(200).json({
      status: 'success',
      data: files,
    });
  } catch (error) {
    console.error('S3 Error:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch files from storage bucket',
      error: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
};

// Get bucket statistics
export const getBucketStats = async (req, res) => {
  try {
    const allObjects = [];
    let continuationToken = undefined;

    // Fetch all objects
    do {
      const command = new ListObjectsV2Command({
        Bucket: process.env.VULTR_BUCKET_NAME,
        ContinuationToken: continuationToken,
      });

      const response = await s3Client.send(command);

      if (response.Contents) {
        allObjects.push(...response.Contents);
      }

      continuationToken = response.NextContinuationToken;
    } while (continuationToken);

    // Calculate statistics
    const totalFiles = allObjects.length;
    const totalSize = allObjects.reduce((sum, obj) => sum + (obj.Size || 0), 0);

    // Group by folder
    const folderMap = new Map();
    allObjects.forEach((obj) => {
      const folder = extractFolder(obj.Key);
      if (!folderMap.has(folder)) {
        folderMap.set(folder, { count: 0, size: 0 });
      }
      const folderStats = folderMap.get(folder);
      folderStats.count += 1;
      folderStats.size += obj.Size || 0;
    });

    // Convert to array and sort by size (descending)
    const folderBreakdown = Array.from(folderMap.entries())
      .map(([folder, stats]) => ({
        folder: folder,
        count: stats.count,
        size: stats.size,
        sizeFormatted: formatBytes(stats.size),
      }))
      .sort((a, b) => b.size - a.size);

    res.status(200).json({
      status: 'success',
      data: {
        totalFiles,
        totalSize,
        totalSizeFormatted: formatBytes(totalSize),
        folderBreakdown,
        lastUpdated: new Date().toISOString(),
      },
    });
  } catch (error) {
    console.error('S3 Error:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch bucket statistics',
      error: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
};
