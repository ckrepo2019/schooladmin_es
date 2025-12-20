import express from 'express';
import {
  getAllEmployees,
  getEmployeeById,
  getUserTypes,
} from '../controllers/employeeController.js';
import {
  getAllMemos,
  getMemoById,
  createMemo,
  updateMemo,
  deleteMemo,
} from '../controllers/memoController.js';
import { verifyToken } from '../middleware/auth.js';
import { uploadMemoFile } from '../middleware/upload.js';

const router = express.Router();

// Employee Profile Routes (all require authentication)
// POST /api/admin/employees - Get all employees (POST to send school db config)
router.post('/employees', verifyToken, getAllEmployees);

// POST /api/admin/employees/:id - Get single employee (POST to send school db config)
router.post('/employees/:id', verifyToken, getEmployeeById);

// POST /api/admin/user-types - Get all user types (POST to send school db config)
router.post('/user-types', verifyToken, getUserTypes);

// Memo Board Routes (all require authentication)
// POST /api/admin/memos - Get all memos
router.post('/memos', verifyToken, getAllMemos);

// POST /api/admin/memos/upload-file - Upload memo file
router.post('/memos/upload-file', verifyToken, (req, res) => {
  uploadMemoFile.single('file')(req, res, (err) => {
    if (err) {
      console.error('Upload error:', err);

      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({
          status: 'error',
          message: 'File size too large. Maximum size is 10MB.',
        });
      }

      return res.status(400).json({
        status: 'error',
        message: err.message || 'Failed to upload file',
      });
    }

    if (!req.file) {
      return res.status(400).json({
        status: 'error',
        message: 'No file uploaded',
      });
    }

    // Construct full S3 URL with protocol
    const fileUrl = req.file.location.startsWith('http')
      ? req.file.location
      : `https://${req.file.location}`;

    // Return the S3 URL
    res.status(200).json({
      status: 'success',
      message: 'File uploaded successfully',
      data: {
        fileUrl: fileUrl,
        fileName: req.file.key,
        originalName: req.file.originalname,
        fileSize: req.file.size,
        mimeType: req.file.mimetype,
      },
    });
  });
});

// POST /api/admin/memos/create - Create new memo (must be before /:id route)
router.post('/memos/create', verifyToken, createMemo);

// POST /api/admin/memos/:id - Get single memo
router.post('/memos/:id', verifyToken, getMemoById);

// PUT /api/admin/memos/:id - Update memo
router.put('/memos/:id', verifyToken, updateMemo);

// DELETE /api/admin/memos/:id - Delete memo
router.delete('/memos/:id', verifyToken, deleteMemo);

export default router;
