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
import {
  getEnrollmentSummary,
  getEnrollmentList,
  getSchoolYears,
  getSemesters,
} from '../controllers/enrollmentController.js';
import {
  getCashierSummary,
  getTransactionList,
  getPaymentTypes,
  getTerminals,
} from '../controllers/cashierController.js';
import {
  getAccountReceivableFilters,
  getAccountReceivableList,
  getAccountReceivableSummary,
} from '../controllers/accountReceivablesController.js';
import {
  getDailyCashSummary,
  getDailyCashItems,
} from '../controllers/dailyCashProgressController.js';
import { getSchoolCalendarEvents } from '../controllers/calendarController.js';
import {
  getMonthlySummary,
  getMonthlySummaryItems,
  getYearlySummary,
  getYearlySummaryTable,
} from '../controllers/summaryController.js';
import {
  getAccountReceivableFilters as getFinanceV1ReceivableFilters,
  getAccountReceivableSummary as getFinanceV1ReceivableSummary,
  getAccountReceivableList as getFinanceV1ReceivableList,
  getCashierSummary as getFinanceV1CashierSummary,
  getTransactionList as getFinanceV1TransactionList,
  getPaymentTypes as getFinanceV1PaymentTypes,
  getTerminals as getFinanceV1Terminals,
  getDailyCashSummary as getFinanceV1DailyCashSummary,
  getDailyCashItems as getFinanceV1DailyCashItems,
  getMonthlySummary as getFinanceV1MonthlySummary,
  getMonthlySummaryItems as getFinanceV1MonthlySummaryItems,
  getYearlySummary as getFinanceV1YearlySummary,
  getYearlySummaryTable as getFinanceV1YearlySummaryTable,
} from '../controllers/financeV1Controller.js';
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

// Enrollment Routes (all require authentication)
// POST /api/admin/enrollment/summary - Get enrollment summary statistics
router.post('/enrollment/summary', verifyToken, getEnrollmentSummary);

// POST /api/admin/enrollment/list - Get detailed enrollment list
router.post('/enrollment/list', verifyToken, getEnrollmentList);

// POST /api/admin/enrollment/school-years - Get available school years
router.post('/enrollment/school-years', verifyToken, getSchoolYears);

// POST /api/admin/enrollment/semesters - Get available semesters
router.post('/enrollment/semesters', verifyToken, getSemesters);

// Cashier Transaction Routes (all require authentication)
// POST /api/admin/cashier/summary - Get cashier summary statistics
router.post('/cashier/summary', verifyToken, getCashierSummary);

// POST /api/admin/cashier/transactions - Get transaction list
router.post('/cashier/transactions', verifyToken, getTransactionList);

// POST /api/admin/cashier/payment-types - Get payment types
router.post('/cashier/payment-types', verifyToken, getPaymentTypes);

// POST /api/admin/cashier/terminals - Get terminals
router.post('/cashier/terminals', verifyToken, getTerminals);

// Accounts Receivable Routes (all require authentication)
// POST /api/admin/receivables/filters - Get account receivables filters
router.post('/receivables/filters', verifyToken, getAccountReceivableFilters);

// POST /api/admin/receivables/summary - Get account receivables summary
router.post('/receivables/summary', verifyToken, getAccountReceivableSummary);

// POST /api/admin/receivables/list - Get account receivables list
router.post('/receivables/list', verifyToken, getAccountReceivableList);

// Daily Cash Progress Routes (all require authentication)
// POST /api/admin/cash-progress/summary - Get daily cash summary
router.post('/cash-progress/summary', verifyToken, getDailyCashSummary);

// POST /api/admin/cash-progress/items - Get daily cash items
router.post('/cash-progress/items', verifyToken, getDailyCashItems);

// Calendar Routes (all require authentication)
// POST /api/admin/calendar/events - Get school calendar events
router.post('/calendar/events', verifyToken, getSchoolCalendarEvents);

// Monthly Summary Routes (all require authentication)
// POST /api/admin/monthly-summary/summary - Get monthly summary analytics
router.post('/monthly-summary/summary', verifyToken, getMonthlySummary);

// POST /api/admin/monthly-summary/items - Get monthly summary items
router.post('/monthly-summary/items', verifyToken, getMonthlySummaryItems);

// Yearly Summary Routes (all require authentication)
// POST /api/admin/yearly-summary/summary - Get yearly summary analytics
router.post('/yearly-summary/summary', verifyToken, getYearlySummary);

// POST /api/admin/yearly-summary/table - Get yearly summary table data
router.post('/yearly-summary/table', verifyToken, getYearlySummaryTable);

// ============================================================================
// FINANCE V1 ROUTES (for schools with finance_v1 = 1)
// ============================================================================

// Finance V1 - Accounts Receivable Routes (all require authentication)
// POST /api/admin/finance-v1/receivables/filters - Get account receivables filters
router.post('/finance-v1/receivables/filters', verifyToken, getFinanceV1ReceivableFilters);

// POST /api/admin/finance-v1/receivables/summary - Get account receivables summary
router.post('/finance-v1/receivables/summary', verifyToken, getFinanceV1ReceivableSummary);

// POST /api/admin/finance-v1/receivables/list - Get account receivables list
router.post('/finance-v1/receivables/list', verifyToken, getFinanceV1ReceivableList);

// Finance V1 - Cashier Transaction Routes (all require authentication)
// POST /api/admin/finance-v1/cashier/summary - Get cashier summary statistics
router.post('/finance-v1/cashier/summary', verifyToken, getFinanceV1CashierSummary);

// POST /api/admin/finance-v1/cashier/transactions - Get transaction list
router.post('/finance-v1/cashier/transactions', verifyToken, getFinanceV1TransactionList);

// POST /api/admin/finance-v1/cashier/payment-types - Get payment types
router.post('/finance-v1/cashier/payment-types', verifyToken, getFinanceV1PaymentTypes);

// POST /api/admin/finance-v1/cashier/terminals - Get terminals
router.post('/finance-v1/cashier/terminals', verifyToken, getFinanceV1Terminals);

// Finance V1 - Daily Cash Progress Routes (all require authentication)
// POST /api/admin/finance-v1/cash-progress/summary - Get daily cash summary
router.post('/finance-v1/cash-progress/summary', verifyToken, getFinanceV1DailyCashSummary);

// POST /api/admin/finance-v1/cash-progress/items - Get daily cash items
router.post('/finance-v1/cash-progress/items', verifyToken, getFinanceV1DailyCashItems);

// Finance V1 - Monthly Summary Routes (all require authentication)
// POST /api/admin/finance-v1/monthly-summary/summary - Get monthly summary analytics
router.post('/finance-v1/monthly-summary/summary', verifyToken, getFinanceV1MonthlySummary);

// POST /api/admin/finance-v1/monthly-summary/items - Get monthly summary items
router.post('/finance-v1/monthly-summary/items', verifyToken, getFinanceV1MonthlySummaryItems);

// Finance V1 - Yearly Summary Routes (all require authentication)
// POST /api/admin/finance-v1/yearly-summary/summary - Get yearly summary analytics
router.post('/finance-v1/yearly-summary/summary', verifyToken, getFinanceV1YearlySummary);

// POST /api/admin/finance-v1/yearly-summary/table - Get yearly summary table data
router.post('/finance-v1/yearly-summary/table', verifyToken, getFinanceV1YearlySummaryTable);

export default router;
