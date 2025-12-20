import multer from 'multer';
import multerS3 from 'multer-s3';
import { S3Client } from '@aws-sdk/client-s3';
import path from 'path';
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
  forcePathStyle: true, // Required for Vultr Object Storage
});

// File filter to accept only images
const imageFileFilter = (req, file, cb) => {
  const allowedTypes = /jpeg|jpg|png|gif|webp|svg/;
  const extname = allowedTypes.test(path.extname(file.originalname).toLowerCase());
  const mimetype = allowedTypes.test(file.mimetype);

  if (mimetype && extname) {
    return cb(null, true);
  } else {
    cb(new Error('Only image files are allowed (jpeg, jpg, png, gif, webp, svg)'));
  }
};

// Configure S3 storage for school logos
const schoolLogoStorage = multerS3({
  s3: s3Client,
  bucket: process.env.VULTR_BUCKET_NAME,
  acl: 'public-read', // Make files publicly accessible
  contentType: multerS3.AUTO_CONTENT_TYPE,
  key: function (req, file, cb) {
    // Generate unique filename: school_logo/timestamp-originalname
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    const ext = path.extname(file.originalname);
    const nameWithoutExt = path.basename(file.originalname, ext);
    const filename = `school_logo/${nameWithoutExt}-${uniqueSuffix}${ext}`;
    cb(null, filename);
  }
});

// Create multer instance for school logo uploads
export const uploadSchoolLogo = multer({
  storage: schoolLogoStorage,
  limits: {
    fileSize: 10 * 1024 * 1024, // 10MB limit
  },
  fileFilter: imageFileFilter,
});

// File filter for memos: images + PDF + DOCX
const memoFileFilter = (req, file, cb) => {
  const allowedTypes = /jpeg|jpg|png|gif|webp|svg|pdf|docx/;
  const extname = allowedTypes.test(path.extname(file.originalname).toLowerCase());
  const allowedMimetypes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];
  const mimetype = allowedMimetypes.includes(file.mimetype);

  if (mimetype && extname) {
    return cb(null, true);
  } else {
    cb(new Error('Only images (JPG, PNG, GIF, WEBP, SVG), PDF, and DOCX files are allowed'));
  }
};

// Configure S3 storage for memo files
const memoFileStorage = multerS3({
  s3: s3Client,
  bucket: process.env.VULTR_BUCKET_NAME,
  acl: 'public-read',
  contentType: multerS3.AUTO_CONTENT_TYPE,
  key: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    const ext = path.extname(file.originalname);
    const nameWithoutExt = path.basename(file.originalname, ext);
    const filename = `memo_files/${nameWithoutExt}-${uniqueSuffix}${ext}`;
    cb(null, filename);
  },
});

// Create multer instance for memo file uploads
export const uploadMemoFile = multer({
  storage: memoFileStorage,
  limits: {
    fileSize: 10 * 1024 * 1024, // 10MB limit
  },
  fileFilter: memoFileFilter,
});
