import express from 'express';
import { login, createUser } from '../controllers/userController.js';
import {
  getAllSchools,
  getSchoolById,
  createSchool,
  updateSchool,
  deleteSchool,
  testConnection,
} from '../controllers/schoolController.js';
import {
  getAllUsers,
  getUserById,
  createUser as createUserManagement,
  updateUser,
  deleteUser,
  updatePassword,
  switchUserAccount,
  restoreSuperAdminSession,
} from '../controllers/userManagementController.js';
import {
  getAllBucketFiles,
  getBucketStats,
} from '../controllers/bucketController.js';
import { verifyToken, verifySuperAdmin } from '../middleware/auth.js';
import { uploadSchoolLogo } from '../middleware/upload.js';

const router = express.Router();

// POST /api/super-admin/login - Super admin login
router.post('/login', login);

// POST /api/super-admin/register - Create new user (super admin only)
router.post('/register', createUser);

// School Management Routes (all require authentication and super-admin role)
// POST /api/super-admin/schools/test-connection - Test database connection (must be before /:id route)
router.post('/schools/test-connection', verifyToken, verifySuperAdmin, testConnection);

// POST /api/super-admin/schools/upload-logo - Upload school logo
router.post('/schools/upload-logo', verifyToken, verifySuperAdmin, (req, res) => {
  uploadSchoolLogo.single('logo')(req, res, (err) => {
    if (err) {
      console.error('Upload error:', err);

      // Handle specific Multer errors
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({
          status: 'error',
          message: 'File size too large. Maximum size is 10MB.',
        });
      }

      if (err.code === 'LIMIT_UNEXPECTED_FILE') {
        return res.status(400).json({
          status: 'error',
          message: 'Unexpected field name. Use "logo" as field name.',
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

    // Return the S3 URL (multer-s3 provides location property)
    const filePath = req.file.location;

    res.status(200).json({
      status: 'success',
      message: 'File uploaded successfully',
      data: {
        filePath,
        filename: req.file.key, // S3 key (path in bucket)
        originalname: req.file.originalname,
        size: req.file.size,
      },
    });
  });
});

// GET /api/super-admin/schools - Get all schools
router.get('/schools', verifyToken, verifySuperAdmin, getAllSchools);

// GET /api/super-admin/schools/:id - Get single school
router.get('/schools/:id', verifyToken, verifySuperAdmin, getSchoolById);

// POST /api/super-admin/schools - Create new school
router.post('/schools', verifyToken, verifySuperAdmin, createSchool);

// PUT /api/super-admin/schools/:id - Update school
router.put('/schools/:id', verifyToken, verifySuperAdmin, updateSchool);

// DELETE /api/super-admin/schools/:id - Delete school
router.delete('/schools/:id', verifyToken, verifySuperAdmin, deleteSchool);

// User Management Routes (all require authentication and super-admin role)
// GET /api/super-admin/users - Get all users (excluding super-admin)
router.get('/users', verifyToken, verifySuperAdmin, getAllUsers);

// GET /api/super-admin/users/:id - Get single user
router.get('/users/:id', verifyToken, verifySuperAdmin, getUserById);

// POST /api/super-admin/users - Create new user
router.post('/users', verifyToken, verifySuperAdmin, createUserManagement);

// PUT /api/super-admin/users/:id - Update user
router.put('/users/:id', verifyToken, verifySuperAdmin, updateUser);

// DELETE /api/super-admin/users/:id - Delete user
router.delete('/users/:id', verifyToken, verifySuperAdmin, deleteUser);

// PUT /api/super-admin/users/:id/password - Update user password
router.put('/users/:id/password', verifyToken, verifySuperAdmin, updatePassword);

// POST /api/super-admin/users/:id/switch - Switch account (impersonate user)
router.post('/users/:id/switch', verifyToken, verifySuperAdmin, switchUserAccount);

// POST /api/super-admin/restore-session - Restore super-admin cookie
router.post('/restore-session', verifyToken, verifySuperAdmin, restoreSuperAdminSession);

// Bucket Monitoring Routes (all require authentication and super-admin role)
// GET /api/super-admin/bucket/files - Get all files in bucket
router.get('/bucket/files', verifyToken, verifySuperAdmin, getAllBucketFiles);

// GET /api/super-admin/bucket/stats - Get bucket storage statistics
router.get('/bucket/stats', verifyToken, verifySuperAdmin, getBucketStats);

export default router;
