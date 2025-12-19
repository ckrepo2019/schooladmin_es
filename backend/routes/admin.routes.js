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

// POST /api/admin/memos/create - Create new memo (must be before /:id route)
router.post('/memos/create', verifyToken, createMemo);

// POST /api/admin/memos/:id - Get single memo
router.post('/memos/:id', verifyToken, getMemoById);

// PUT /api/admin/memos/:id - Update memo
router.put('/memos/:id', verifyToken, updateMemo);

// DELETE /api/admin/memos/:id - Delete memo
router.delete('/memos/:id', verifyToken, deleteMemo);

export default router;
