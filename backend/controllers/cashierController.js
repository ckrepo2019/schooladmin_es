import mysql from 'mysql2/promise';

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

// Get cashier summary statistics
export const getCashierSummary = async (req, res) => {
  try {
    const {
      schoolDbConfig,
      syid,
      semid,
      startDate,
      endDate,
      paymentTypeId,
      terminalId,
      status,
    } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const schoolDb = await getSchoolConnection(schoolDbConfig);

    const baseParams = [syid || 0, semid || 0];
    const extraFilters = [];
    const extraParams = [];

    if (startDate) {
      extraFilters.push('DATE(t.transdate) >= ?');
      extraParams.push(startDate);
    }

    if (endDate) {
      extraFilters.push('DATE(t.transdate) <= ?');
      extraParams.push(endDate);
    }

    if (paymentTypeId) {
      extraFilters.push('t.paymenttype_id = ?');
      extraParams.push(paymentTypeId);
    }

    if (terminalId) {
      extraFilters.push('t.terminalno = ?');
      extraParams.push(terminalId);
    }

    let statusClause = '';
    if (status === 'posted') {
      statusClause = ' AND t.cancelled = 0 AND t.posted = 1';
    } else if (status === 'cancelled') {
      statusClause = ' AND t.cancelled = 1';
    } else if (status === 'pending') {
      statusClause = ' AND t.cancelled = 0 AND (t.posted = 0 OR t.posted IS NULL)';
    }

    const breakdownStatusClause = statusClause || ' AND t.cancelled = 0';
    const extraClause = extraFilters.length ? ` AND ${extraFilters.join(' AND ')}` : '';

    // Get total transactions and amounts
    const [totalStats] = await schoolDb.execute(
      `SELECT
        COUNT(*) as total_transactions,
        SUM(totalamount) as total_collections,
        SUM(CASE WHEN cancelled = 1 THEN totalamount ELSE 0 END) as cancelled_amount,
        SUM(CASE WHEN cancelled = 1 THEN 1 ELSE 0 END) as cancelled_count,
        SUM(
          CASE
            WHEN cancelled = 0
              AND IFNULL(change_amount, 0) = 0
              AND IFNULL(amountpaid, 0) > IFNULL(totalamount, 0)
              THEN IFNULL(amountpaid, 0) - IFNULL(totalamount, 0)
            ELSE 0
          END
        ) as overpayment_amount,
        SUM(
          CASE
            WHEN cancelled = 0
              AND IFNULL(change_amount, 0) = 0
              AND IFNULL(amountpaid, 0) > IFNULL(totalamount, 0)
              THEN 1
            ELSE 0
          END
        ) as overpayment_count
      FROM chrngtrans t
      WHERE t.syid = ? AND t.semid = ?${statusClause}${extraClause}`,
      [...baseParams, ...extraParams]
    );

    // Get collections by payment type
    const [byPaymentType] = await schoolDb.execute(
      `SELECT
        pt.description as payment_type,
        COUNT(t.id) as transaction_count,
        SUM(t.totalamount) as total_amount
      FROM chrngtrans t
      LEFT JOIN paymenttype pt ON t.paymenttype_id = pt.id
      WHERE t.syid = ? AND t.semid = ?${breakdownStatusClause}${extraClause}
      GROUP BY t.paymenttype_id, pt.description
      ORDER BY total_amount DESC`,
      [...baseParams, ...extraParams]
    );

    // Get collections by item classification
    const [byClassification] = await schoolDb.execute(
      `SELECT
        ic.description as classification,
        COUNT(DISTINCT ti.chrngtransid) as transaction_count,
        SUM(ti.amount) as total_amount
      FROM chrngtransitems ti
      LEFT JOIN itemclassification ic ON ti.classid = ic.id
      LEFT JOIN chrngtrans t ON ti.chrngtransid = t.id
      WHERE t.syid = ? AND t.semid = ?${breakdownStatusClause} AND ti.deleted = 0${extraClause}
      GROUP BY ti.classid, ic.description
      ORDER BY total_amount DESC`,
      [...baseParams, ...extraParams]
    );

    // Get collections by terminal
    const [byTerminal] = await schoolDb.execute(
      `SELECT
        term.description as terminal,
        term.owner as cashier,
        COUNT(t.id) as transaction_count,
        SUM(t.totalamount) as total_amount
      FROM chrngtrans t
      LEFT JOIN chrngterminals term ON t.terminalno = term.id
      WHERE t.syid = ? AND t.semid = ?${breakdownStatusClause}${extraClause}
      GROUP BY t.terminalno, term.description, term.owner
      ORDER BY total_amount DESC`,
      [...baseParams, ...extraParams]
    );

    // Get daily collections for the last 7 days
    const [dailyCollections] = await schoolDb.execute(
      `SELECT
        DATE(transdate) as date,
        COUNT(id) as transaction_count,
        SUM(totalamount) as total_amount
      FROM chrngtrans t
      WHERE t.syid = ? AND t.semid = ?${breakdownStatusClause}
        AND t.transdate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)${extraClause}
      GROUP BY DATE(transdate)
      ORDER BY date DESC`,
      [...baseParams, ...extraParams]
    );

    await schoolDb.end();

    res.status(200).json({
      status: 'success',
      data: {
        summary: totalStats[0],
        byPaymentType,
        byClassification,
        byTerminal,
        dailyCollections,
      },
    });
  } catch (error) {
    console.error('Error fetching cashier summary:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch cashier summary',
      error: error.message,
    });
  }
};

// Get transaction list
export const getTransactionList = async (req, res) => {
  try {
    const {
      schoolDbConfig,
      syid,
      semid,
      startDate,
      endDate,
      paymentTypeId,
      terminalId,
      status,
    } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const schoolDb = await getSchoolConnection(schoolDbConfig);

    let query = `
      SELECT
        t.id,
        t.ornum,
        t.transdate,
        t.studid,
        s.sid as sid,
        TRIM(CONCAT_WS(' ', s.firstname, s.middlename, s.lastname)) as full_name,
        t.studname,
        t.glevel as grade_level,
        t.totalamount,
        t.amountpaid,
        t.amounttendered,
        t.change_amount,
        pt.description as payment_type,
        term.description as terminal,
        term.owner as cashier,
        t.transby,
        TRIM(CONCAT_WS(' ', tr.lastname, tr.firstname, tr.middlename)) as transacted_by,
        t.cancelled,
        t.cancelledremarks,
        t.posted,
        GROUP_CONCAT(DISTINCT ic.description ORDER BY ic.description SEPARATOR ', ') as items
      FROM chrngtrans t
      LEFT JOIN studinfo s ON t.studid = s.id
      LEFT JOIN teacher tr ON t.transby = tr.userid
      LEFT JOIN paymenttype pt ON t.paymenttype_id = pt.id
      LEFT JOIN chrngterminals term ON t.terminalno = term.id
      LEFT JOIN chrngtransitems ti ON t.id = ti.chrngtransid AND ti.deleted = 0
      LEFT JOIN itemclassification ic ON ti.classid = ic.id
      WHERE t.syid = ? AND t.semid = ?
    `;

    const params = [syid || 0, semid || 0];

    // Add date filter if provided
    if (startDate) {
      query += ` AND DATE(t.transdate) >= ?`;
      params.push(startDate);
    }

    if (endDate) {
      query += ` AND DATE(t.transdate) <= ?`;
      params.push(endDate);
    }

    // Add payment type filter
    if (paymentTypeId) {
      query += ` AND t.paymenttype_id = ?`;
      params.push(paymentTypeId);
    }

    // Add terminal filter
    if (terminalId) {
      query += ` AND t.terminalno = ?`;
      params.push(terminalId);
    }

    // Add status filter
    if (status === 'posted') {
      query += ` AND t.cancelled = 0 AND t.posted = 1`;
    } else if (status === 'cancelled') {
      query += ` AND t.cancelled = 1`;
    } else if (status === 'pending') {
      query += ` AND t.cancelled = 0 AND (t.posted = 0 OR t.posted IS NULL)`;
    }

    query += `
      GROUP BY t.id, t.ornum, t.transdate, t.studid, s.sid, s.firstname, s.middlename, s.lastname,
               t.studname, t.glevel, t.transby, tr.lastname, tr.firstname, tr.middlename,
               t.totalamount, t.amountpaid, t.amounttendered, t.change_amount,
               pt.description, term.description, term.owner, t.cancelled, t.cancelledremarks, t.posted
      ORDER BY t.transdate DESC, t.id DESC
    `;

    const [transactions] = await schoolDb.execute(query, params);

    await schoolDb.end();

    res.status(200).json({
      status: 'success',
      data: transactions,
    });
  } catch (error) {
    console.error('Error fetching transaction list:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch transaction list',
      error: error.message,
    });
  }
};

// Get payment types
export const getPaymentTypes = async (req, res) => {
  try {
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const schoolDb = await getSchoolConnection(schoolDbConfig);

    const [paymentTypes] = await schoolDb.execute(
      'SELECT * FROM paymenttype WHERE deleted = 0 ORDER BY id'
    );

    await schoolDb.end();

    res.status(200).json({
      status: 'success',
      data: paymentTypes,
    });
  } catch (error) {
    console.error('Error fetching payment types:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch payment types',
      error: error.message,
    });
  }
};

// Get terminals
export const getTerminals = async (req, res) => {
  try {
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    const schoolDb = await getSchoolConnection(schoolDbConfig);

    const [terminals] = await schoolDb.execute(
      'SELECT * FROM chrngterminals ORDER BY id'
    );

    await schoolDb.end();

    res.status(200).json({
      status: 'success',
      data: terminals,
    });
  } catch (error) {
    console.error('Error fetching terminals:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch terminals',
      error: error.message,
    });
  }
};
