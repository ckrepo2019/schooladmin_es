import mysql from 'mysql2/promise';

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

const pad2 = (value) => String(value).padStart(2, '0');

const formatDateKey = (date) =>
  `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;

const parseDateOnly = (value) => {
  if (!value) return null;
  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) return null;
    return new Date(value.getFullYear(), value.getMonth(), value.getDate());
  }
  const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
  if (!year || !month || !day) return null;
  return new Date(year, month - 1, day);
};

const formatMonthKey = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`;

const formatMonthLabel = (key) => {
  const [year, month] = key.split('-').map(Number);
  if (!year || !month) return key;
  const date = new Date(year, month - 1, 1);
  return date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
};

const buildMonthRange = (startDate, endDate) => {
  const months = [];
  if (!startDate || !endDate) return months;
  const current = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
  const last = new Date(endDate.getFullYear(), endDate.getMonth(), 1);

  while (current <= last) {
    const key = formatMonthKey(current);
    months.push({ key, label: formatMonthLabel(key) });
    current.setMonth(current.getMonth() + 1);
  }

  return months;
};

const buildFilters = ({ startDate, endDate, paymentTypeId, status }) => {
  const params = [];
  let clause = '';

  const resolvedStatus = status && status !== 'all' ? status : null;
  if (resolvedStatus === 'posted') {
    clause += ' AND t.cancelled = 0 AND t.posted = 1';
  } else if (resolvedStatus === 'cancelled') {
    clause += ' AND t.cancelled = 1';
  } else if (resolvedStatus === 'pending') {
    clause += ' AND t.cancelled = 0 AND (t.posted = 0 OR t.posted IS NULL)';
  } else {
    clause += ' AND t.cancelled = 0';
  }

  if (startDate) {
    clause += ' AND DATE(t.transdate) >= ?';
    params.push(startDate);
  }

  if (endDate) {
    clause += ' AND DATE(t.transdate) <= ?';
    params.push(endDate);
  }

  if (paymentTypeId) {
    clause += ' AND t.paymenttype_id = ?';
    params.push(paymentTypeId);
  }

  return { clause, params };
};

const normalizeItemLabel = "COALESCE(NULLIF(TRIM(cct.particulars), ''), 'Unspecified')";

export const getMonthlySummary = async (req, res) => {
  try {
    const { schoolDbConfig, startDate, endDate, paymentTypeId, status } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const db = await getSchoolConnection(schoolDbConfig);
    const { clause, params } = buildFilters({ startDate, endDate, paymentTypeId, status });

    const [summaryRows] = await db.execute(
      `
        SELECT
          COUNT(DISTINCT t.transno) as transaction_count,
          COUNT(cct.id) as line_count,
          COUNT(DISTINCT ${normalizeItemLabel}) as item_count,
          SUM(cct.amount) as total_amount
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0${clause}
      `,
      params
    );

    const [byItem] = await db.execute(
      `
        SELECT
          ${normalizeItemLabel} as item,
          SUM(cct.amount) as total_amount,
          COUNT(DISTINCT t.transno) as transaction_count
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0${clause}
        GROUP BY item
        ORDER BY total_amount DESC
      `,
      params
    );

    const [byMonth] = await db.execute(
      `
        SELECT
          DATE_FORMAT(t.transdate, '%Y-%m') as month_key,
          SUM(cct.amount) as total_amount,
          COUNT(DISTINCT t.transno) as transaction_count
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0${clause}
        GROUP BY month_key
        ORDER BY month_key
      `,
      params
    );

    await db.end();

    res.status(200).json({
      status: 'success',
      data: {
        summary: summaryRows[0] || null,
        byItem,
        byMonth: byMonth.map((item) => ({
          ...item,
          month_label: formatMonthLabel(item.month_key),
        })),
      },
    });
  } catch (error) {
    console.error('Error fetching monthly summary:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch monthly summary',
      error: error.message,
    });
  }
};

export const getMonthlySummaryItems = async (req, res) => {
  try {
    const { schoolDbConfig, startDate, endDate, paymentTypeId, status } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const db = await getSchoolConnection(schoolDbConfig);
    const { clause, params } = buildFilters({ startDate, endDate, paymentTypeId, status });

    const [byItem] = await db.execute(
      `
        SELECT
          ${normalizeItemLabel} as item,
          SUM(cct.amount) as total_amount,
          COUNT(DISTINCT t.transno) as transaction_count
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0${clause}
        GROUP BY item
        ORDER BY total_amount DESC
      `,
      params
    );

    await db.end();

    res.status(200).json({
      status: 'success',
      data: byItem || [],
    });
  } catch (error) {
    console.error('Error fetching monthly summary items:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch monthly summary items',
      error: error.message,
    });
  }
};

export const getYearlySummary = async (req, res) => {
  try {
    const { schoolDbConfig, syid, paymentTypeId, status } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    if (!syid) {
      return res.status(400).json({
        status: 'error',
        message: 'School year is required',
      });
    }

    const db = await getSchoolConnection(schoolDbConfig);

    const [syRows] = await db.execute(
      'SELECT id, sydesc, sdate, edate FROM sy WHERE id = ? LIMIT 1',
      [syid]
    );

    if (syRows.length === 0) {
      await db.end();
      return res.status(404).json({
        status: 'error',
        message: 'School year not found',
      });
    }

    const schoolYear = syRows[0];
    const syStart = parseDateOnly(schoolYear.sdate);
    const syEnd = parseDateOnly(schoolYear.edate);

    if (!syStart || !syEnd) {
      await db.end();
      return res.status(400).json({
        status: 'error',
        message: 'School year date range is invalid',
      });
    }

    const startDate = formatDateKey(syStart);
    const endDate = formatDateKey(syEnd);
    const { clause, params } = buildFilters({
      startDate,
      endDate,
      paymentTypeId,
      status,
    });

    const baseParams = [syid, ...params];

    const [summaryRows] = await db.execute(
      `
        SELECT
          COUNT(DISTINCT t.transno) as transaction_count,
          COUNT(cct.id) as line_count,
          COUNT(DISTINCT ${normalizeItemLabel}) as item_count,
          SUM(cct.amount) as total_amount
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0 AND t.syid = ?${clause}
      `,
      baseParams
    );

    const [byMonth] = await db.execute(
      `
        SELECT
          DATE_FORMAT(t.transdate, '%Y-%m') as month_key,
          SUM(cct.amount) as total_amount,
          COUNT(DISTINCT t.transno) as transaction_count
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0 AND t.syid = ?${clause}
        GROUP BY month_key
        ORDER BY month_key
      `,
      baseParams
    );

    const [byItem] = await db.execute(
      `
        SELECT
          ${normalizeItemLabel} as item,
          SUM(cct.amount) as total_amount,
          COUNT(DISTINCT t.transno) as transaction_count
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0 AND t.syid = ?${clause}
        GROUP BY item
        ORDER BY total_amount DESC
      `,
      baseParams
    );

    await db.end();

    res.status(200).json({
      status: 'success',
      data: {
        schoolYear: {
          id: schoolYear.id,
          sydesc: schoolYear.sydesc,
          startDate,
          endDate,
        },
        summary: summaryRows[0] || null,
        byMonth: byMonth.map((item) => ({
          ...item,
          month_label: formatMonthLabel(item.month_key),
        })),
        byItem,
      },
    });
  } catch (error) {
    console.error('Error fetching yearly summary:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch yearly summary',
      error: error.message,
    });
  }
};

export const getYearlySummaryTable = async (req, res) => {
  try {
    const { schoolDbConfig, syid, paymentTypeId, status } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    if (!syid) {
      return res.status(400).json({
        status: 'error',
        message: 'School year is required',
      });
    }

    const db = await getSchoolConnection(schoolDbConfig);

    const [syRows] = await db.execute(
      'SELECT id, sydesc, sdate, edate FROM sy WHERE id = ? LIMIT 1',
      [syid]
    );

    if (syRows.length === 0) {
      await db.end();
      return res.status(404).json({
        status: 'error',
        message: 'School year not found',
      });
    }

    const schoolYear = syRows[0];
    const syStart = parseDateOnly(schoolYear.sdate);
    const syEnd = parseDateOnly(schoolYear.edate);

    if (!syStart || !syEnd) {
      await db.end();
      return res.status(400).json({
        status: 'error',
        message: 'School year date range is invalid',
      });
    }

    const startDate = formatDateKey(syStart);
    const endDate = formatDateKey(syEnd);
    const months = buildMonthRange(syStart, syEnd);
    const { clause, params } = buildFilters({
      startDate,
      endDate,
      paymentTypeId,
      status,
    });

    const baseParams = [syid, ...params];

    const [rows] = await db.execute(
      `
        SELECT
          ${normalizeItemLabel} as item,
          DATE_FORMAT(t.transdate, '%Y-%m') as month_key,
          SUM(cct.amount) as total_amount
        FROM chrngcashtrans cct
        JOIN chrngtrans t ON t.transno = cct.transno
        WHERE cct.deleted = 0 AND t.syid = ?${clause}
        GROUP BY item, month_key
        ORDER BY item, month_key
      `,
      baseParams
    );

    await db.end();

    const itemMap = new Map();

    rows.forEach((row) => {
      const itemName = row.item || 'Unspecified';
      if (!itemMap.has(itemName)) {
        itemMap.set(itemName, {
          item: itemName,
          total_amount: 0,
          monthly: {},
        });
      }

      const entry = itemMap.get(itemName);
      const amount = Number(row.total_amount) || 0;
      entry.monthly[row.month_key] = amount;
      entry.total_amount += amount;
    });

    const items = Array.from(itemMap.values()).map((entry) => ({
      ...entry,
      total_amount: Number(entry.total_amount.toFixed(2)),
    }));

    res.status(200).json({
      status: 'success',
      data: {
        schoolYear: {
          id: schoolYear.id,
          sydesc: schoolYear.sydesc,
          startDate,
          endDate,
        },
        months,
        items,
      },
    });
  } catch (error) {
    console.error('Error fetching yearly summary table:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch yearly summary table',
      error: error.message,
    });
  }
};
