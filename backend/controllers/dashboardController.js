import mysql from 'mysql2/promise';
import db from '../config/db.js';
import { getAccountReceivableSummaryTotals } from './accountReceivablesController.js';
import { getFinanceV1ReceivableSummaryTotals } from './financeV1Controller.js';

/**
 * Get school database connection
 */
const getSchoolConnection = async (schoolDbConfig) => {
  const parsedPort = Number.parseInt(schoolDbConfig.db_port, 10);
  const resolvedPort = Number.isNaN(parsedPort)
    ? Number.parseInt(process.env.DB_PORT, 10) || 3306
    : parsedPort;
  const connection = await mysql.createConnection({
    host: schoolDbConfig.db_host || process.env.DB_HOST || 'localhost',
    user: schoolDbConfig.db_username,
    password: schoolDbConfig.db_password || '',
    database: schoolDbConfig.db_name,
    port: resolvedPort,
  });
  return connection;
};

/**
 * Check if finance_v1 is enabled for this school
 */
const isFinanceV1 = async (schoolDbConfig) => {
  if (!schoolDbConfig?.db_name) return false;

  if (schoolDbConfig.finance_v1 !== undefined && schoolDbConfig.finance_v1 !== null) {
    return Number(schoolDbConfig.finance_v1) === 1;
  }

  try {
    const [rows] = await db.query(
      'SELECT finance_v1 FROM schools WHERE db_name = ? LIMIT 1',
      [schoolDbConfig.db_name]
    );
    return rows?.[0]?.finance_v1 === 1;
  } catch {
    return false;
  }
};

/**
 * Get comprehensive dashboard data for admin portal
 */
export const getDashboardData = async (req, res) => {
  let schoolDb = null;

  try {
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    schoolDb = await getSchoolConnection(schoolDbConfig);
    const useFinanceV1 = await isFinanceV1(schoolDbConfig);

    // Get active school year and semester
    const [activeSchoolYear] = await schoolDb.execute(
      'SELECT * FROM sy WHERE isactive = 1 LIMIT 1'
    );
    const [activeSemester] = await schoolDb.execute(
      'SELECT * FROM semester WHERE isactive = 1 LIMIT 1'
    );

    const activeSyId = activeSchoolYear[0]?.id || 0;
    const activeSemId = activeSemester[0]?.id || 0;

    // === ENROLLMENT DATA ===
    // Total enrolled students across all programs
    const [gsCount] = await schoolDb.execute(
      `SELECT COUNT(*) as count FROM enrolledstud WHERE deleted = 0 AND syid = ? AND studstatus IN (1, 2)`,
      [activeSyId]
    );
    const [shsCount] = await schoolDb.execute(
      `SELECT COUNT(*) as count FROM sh_enrolledstud WHERE deleted = 0 AND syid = ? AND semid = ? AND studstatus IN (1, 2)`,
      [activeSyId, activeSemId]
    );
    const [collegeCount] = await schoolDb.execute(
      `SELECT COUNT(*) as count FROM college_enrolledstud WHERE deleted = 0 AND syid = ? AND semid = ? AND studstatus IN (1, 2)`,
      [activeSyId, activeSemId]
    );

    const totalEnrolled = Number(gsCount[0]?.count || 0) + Number(shsCount[0]?.count || 0) + Number(collegeCount[0]?.count || 0);

    // Gender distribution
    const [genderDist] = await schoolDb.execute(
      `SELECT
        CASE
          WHEN UPPER(s.gender) IN ('M', 'MALE') THEN 'Male'
          WHEN UPPER(s.gender) IN ('F', 'FEMALE') THEN 'Female'
          ELSE 'Other'
        END as gender,
        COUNT(*) as count
      FROM (
        SELECT s.gender FROM enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.studstatus IN (1, 2)
        UNION ALL
        SELECT s.gender FROM sh_enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.semid = ? AND e.studstatus IN (1, 2)
        UNION ALL
        SELECT s.gender FROM college_enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.semid = ? AND e.studstatus IN (1, 2)
      ) AS combined
      JOIN studinfo s ON 1=0
      GROUP BY gender
      HAVING gender IN ('Male', 'Female')`,
      [activeSyId, activeSyId, activeSemId, activeSyId, activeSemId]
    );

    // Fix gender query - direct approach
    const [genderData] = await schoolDb.execute(
      `SELECT
        SUM(CASE WHEN UPPER(gender) IN ('M', 'MALE') THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN UPPER(gender) IN ('F', 'FEMALE') THEN 1 ELSE 0 END) as female
      FROM (
        SELECT s.gender FROM enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.studstatus IN (1, 2)
        UNION ALL
        SELECT s.gender FROM sh_enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.semid = ? AND e.studstatus IN (1, 2)
        UNION ALL
        SELECT s.gender FROM college_enrolledstud e
        JOIN studinfo s ON e.studid = s.id
        WHERE e.deleted = 0 AND e.syid = ? AND e.semid = ? AND e.studstatus IN (1, 2)
      ) AS combined`,
      [activeSyId, activeSyId, activeSemId, activeSyId, activeSemId]
    );

    const studentGender = [
      { label: 'Male', value: Number(genderData[0]?.male || 0) },
      { label: 'Female', value: Number(genderData[0]?.female || 0) },
    ];

    // Grade level distribution
    const [gradeLevels] = await schoolDb.execute(
      `SELECT
        COALESCE(l.levelname, 'Unknown') as label,
        COUNT(*) as value
      FROM enrolledstud e
      LEFT JOIN gradelevel l ON e.levelid = l.id
      WHERE e.deleted = 0 AND e.syid = ? AND e.studstatus IN (1, 2)
      GROUP BY e.levelid, l.levelname, l.sortid
      ORDER BY l.sortid`,
      [activeSyId]
    );

    // Pending enrollments
    const [pendingEnrollments] = await schoolDb.execute(
      `SELECT COUNT(*) as count FROM (
        SELECT id FROM enrolledstud WHERE deleted = 0 AND syid = ? AND studstatus = 0
        UNION ALL
        SELECT id FROM sh_enrolledstud WHERE deleted = 0 AND syid = ? AND semid = ? AND studstatus = 0
        UNION ALL
        SELECT id FROM college_enrolledstud WHERE deleted = 0 AND syid = ? AND semid = ? AND studstatus = 0
      ) AS pending`,
      [activeSyId, activeSyId, activeSemId, activeSyId, activeSemId]
    );

    // === EMPLOYEE DATA ===
    const [employeeCount] = await schoolDb.execute(
      `SELECT COUNT(DISTINCT t.id) as count FROM teacher t WHERE t.deleted = 0`
    );

    // Employee by user type/department
    const [employeesByType] = await schoolDb.execute(
      `SELECT
        COALESCE(ut.utype, 'Unassigned') as label,
        COUNT(*) as value
      FROM teacher t
      LEFT JOIN users u ON t.userid = u.id
      LEFT JOIN usertype ut ON u.type = ut.id
      WHERE t.deleted = 0
      GROUP BY ut.utype
      ORDER BY value DESC
      LIMIT 5`
    );

    // === FINANCE DATA ===
    const today = new Date().toISOString().split('T')[0];
    const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    let collectionsToday = 0;
    let collectionsMTD = 0;
    let receivables = 0;
    let receivablesFallback = 0;
    let monthlyCollections = [];

    if (useFinanceV1) {
      // ===========================================
      // FINANCE V1 LOGIC
      // Collections: from chrngtrans table (no semid filter, no paymenttype_id)
      // Receivables: from studledger table (amount - discount - payments)
      // ===========================================

      // Collections today from chrngtrans (Finance V1 uses chrngtrans for transactions)
      try {
        const [todayCollections] = await schoolDb.execute(
          `SELECT COALESCE(SUM(totalamount), 0) as total FROM chrngtrans
           WHERE cancelled = 0 AND DATE(transdate) = ? AND syid = ?`,
          [today, activeSyId]
        );
        collectionsToday = Number(todayCollections[0]?.total || 0);
      } catch {
        collectionsToday = 0;
      }

      // Collections MTD from chrngtrans
      try {
        const [mtdCollections] = await schoolDb.execute(
          `SELECT COALESCE(SUM(totalamount), 0) as total FROM chrngtrans
           WHERE cancelled = 0 AND DATE(transdate) >= ? AND DATE(transdate) <= ? AND syid = ?`,
          [monthStart, today, activeSyId]
        );
        collectionsMTD = Number(mtdCollections[0]?.total || 0);
      } catch {
        collectionsMTD = 0;
      }

      // Receivables from studledger (Finance V1 approach)
      // Logic: totalassessment(amount) - discount - payments = balance
      try {
        const [receivablesData] = await schoolDb.execute(
          `SELECT
            COALESCE(SUM(amount), 0) -
            COALESCE(SUM(CASE WHEN particulars LIKE '%DISCOUNT:%' THEN payment ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN particulars NOT LIKE '%DISCOUNT:%' THEN payment ELSE 0 END), 0) as balance
           FROM studledger
           WHERE deleted = 0 AND syid = ?`,
          [activeSyId]
        );
        receivablesFallback = Math.max(0, Number(receivablesData[0]?.balance || 0));
      } catch {
        receivablesFallback = 0;
      }

      // Monthly collections for last 12 months from chrngtrans
      try {
        const [monthlyData] = await schoolDb.execute(
          `SELECT
            DATE_FORMAT(transdate, '%Y-%m') as month,
            COALESCE(SUM(totalamount), 0) as total
          FROM chrngtrans
          WHERE cancelled = 0 AND transdate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(transdate, '%Y-%m')
          ORDER BY month`
        );
        monthlyCollections = monthlyData;
      } catch {
        monthlyCollections = [];
      }
    } else {
      // ===========================================
      // FINANCE V2 LOGIC
      // Collections: from chrngtrans table (with semid filter)
      // Receivables: from enrolledstud.balance field
      // ===========================================

      // Collections today from chrngtrans
      try {
        const [todayCollections] = await schoolDb.execute(
          `SELECT COALESCE(SUM(totalamount), 0) as total FROM chrngtrans
           WHERE cancelled = 0 AND DATE(transdate) = ? AND syid = ? AND semid = ?`,
          [today, activeSyId, activeSemId]
        );
        collectionsToday = Number(todayCollections[0]?.total || 0);
      } catch {
        collectionsToday = 0;
      }

      // Collections MTD from chrngtrans
      try {
        const [mtdCollections] = await schoolDb.execute(
          `SELECT COALESCE(SUM(totalamount), 0) as total FROM chrngtrans
           WHERE cancelled = 0 AND DATE(transdate) >= ? AND DATE(transdate) <= ? AND syid = ? AND semid = ?`,
          [monthStart, today, activeSyId, activeSemId]
        );
        collectionsMTD = Number(mtdCollections[0]?.total || 0);
      } catch {
        collectionsMTD = 0;
      }

      // Receivables from enrolledstud.balance (Finance V2 approach)
      try {
        const [receivablesData] = await schoolDb.execute(
          `SELECT COALESCE(SUM(balance), 0) as balance FROM enrolledstud
           WHERE deleted = 0 AND syid = ? AND balance > 0`,
          [activeSyId]
        );
        receivablesFallback = Number(receivablesData[0]?.balance || 0);
      } catch {
        receivablesFallback = 0;
      }

      // Monthly collections for last 12 months from chrngtrans
      try {
        const [monthlyData] = await schoolDb.execute(
          `SELECT
            DATE_FORMAT(transdate, '%Y-%m') as month,
            COALESCE(SUM(totalamount), 0) as total
          FROM chrngtrans
          WHERE cancelled = 0 AND transdate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(transdate, '%Y-%m')
          ORDER BY month`
        );
        monthlyCollections = monthlyData;
      } catch {
        monthlyCollections = [];
      }
    }

    let summaryReceivables = null;
    try {
      if (useFinanceV1) {
        const summaryData = await getFinanceV1ReceivableSummaryTotals({
          schoolDbConfig,
          syid: activeSyId,
          semid: activeSemId,
        });
        const totalReceivable = Number(summaryData?.summary?.total_receivable);
        if (Number.isFinite(totalReceivable)) {
          summaryReceivables = totalReceivable;
        }
      } else {
        const summaryData = await getAccountReceivableSummaryTotals({
          schoolDbConfig,
          syid: activeSyId,
          semid: activeSemId,
        });
        const totalReceivable = Number(summaryData?.summary?.total_receivable);
        if (Number.isFinite(totalReceivable)) {
          summaryReceivables = totalReceivable;
        }
      }
    } catch (error) {
      console.error('Error computing dashboard receivables summary:', error);
    }

    receivables = summaryReceivables !== null ? summaryReceivables : receivablesFallback;

    // Build 12-month collections array
    const monthLabels = [];
    const collectionsData = [];
    const now = new Date();
    for (let i = 11; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const monthKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      monthLabels.push(d.toLocaleString('en-US', { month: 'short' }));
      const found = monthlyCollections.find((m) => m.month === monthKey);
      collectionsData.push(Number(found?.total || 0));
    }

    // === CALENDAR EVENTS ===
    const thirtyDaysLater = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    let upcomingEvents = [];
    let upcomingEventsCount = 0;

    try {
      const [events] = await schoolDb.execute(
        `SELECT id, title, start, end, venue, holiday, isnoclass
         FROM schoolcalendar
         WHERE deleted = 0 AND DATE(start) >= CURDATE() AND DATE(start) <= ?
         ORDER BY start ASC
         LIMIT 10`,
        [thirtyDaysLater]
      );
      upcomingEvents = events.map((e) => ({
        id: e.id,
        title: e.title,
        when: new Date(e.start).toLocaleDateString(),
        venue: e.venue,
      }));
      upcomingEventsCount = events.length;
    } catch {
      // Table might not exist
    }

    // === MEMO DATA ===
    let recentMemos = [];
    let memosThisWeek = 0;

    try {
      const [memos] = await schoolDb.execute(
        `SELECT id, title, created_at, recipient_type
         FROM memos
         WHERE deleted = 0
         ORDER BY created_at DESC
         LIMIT 5`
      );
      recentMemos = memos.map((m) => ({
        id: m.id,
        title: m.title,
        audience: m.recipient_type || 'All',
      }));

      const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
      const [weekMemos] = await schoolDb.execute(
        `SELECT COUNT(*) as count FROM memos WHERE deleted = 0 AND DATE(created_at) >= ?`,
        [weekAgo]
      );
      memosThisWeek = Number(weekMemos[0]?.count || 0);
    } catch {
      // Table might not exist
    }

    await schoolDb.end();

    // Build response
    res.status(200).json({
      status: 'success',
      data: {
        kpis: {
          enrolledStudents: totalEnrolled,
          pendingEnrollments: Number(pendingEnrollments[0]?.count || 0),
          employees: Number(employeeCount[0]?.count || 0),
          collectionsToday,
          collectionsMTD,
          receivables,
          upcomingEvents: upcomingEventsCount,
          memosThisWeek,
        },
        finance: {
          monthLabels,
          collectionsData,
        },
        charts: {
          studentGender,
          gradeLevels: gradeLevels.map((g) => ({ label: g.label, value: Number(g.value) })),
          departments: employeesByType.map((e) => ({ label: e.label, value: Number(e.value) })),
        },
        lists: {
          events: upcomingEvents,
          memos: recentMemos,
        },
        activeSchoolYear: activeSchoolYear[0] || null,
        activeSemester: activeSemester[0] || null,
      },
    });
  } catch (error) {
    if (schoolDb) {
      try {
        await schoolDb.end();
      } catch {
        // Ignore close error
      }
    }

    console.error('Error fetching dashboard data:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch dashboard data',
      error: error.message,
    });
  }
};
