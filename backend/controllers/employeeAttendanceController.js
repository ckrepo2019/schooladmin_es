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

const resolveBoolean = (value) => value === true || value === 1 || value === '1' || value === 'true';

/**
 * Get employee attendance records from taphistory
 */
export const getEmployeeAttendance = async (req, res) => {
  let connection = null;

  try {
    const {
      schoolDbConfig,
      startDate,
      endDate,
      usertypeId,
      tapstate,
      includeStudents,
      limit,
    } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    connection = await getSchoolConnection(schoolDbConfig);

    const optionalColumns = ['timeshift', 'tapstatus', 'station_id', 'mode', 'updated_at'];
    const availableColumns = new Set();
    try {
      const placeholders = optionalColumns.map(() => '?').join(',');
      const [columnRows] = await connection.execute(
        `SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = 'taphistory'
           AND COLUMN_NAME IN (${placeholders})`,
        [schoolDbConfig.db_name, ...optionalColumns]
      );
      for (const row of columnRows || []) {
        if (row?.COLUMN_NAME) {
          availableColumns.add(row.COLUMN_NAME);
        }
      }
    } catch (schemaError) {
      console.warn('Unable to probe taphistory columns:', schemaError?.message || schemaError);
    }

    const conditions = ['COALESCE(th.deleted, 0) = 0'];
    const params = [];

    const includeStudentsResolved = resolveBoolean(includeStudents);
    if (!includeStudentsResolved) {
      conditions.push('(th.utype <> 7 OR th.utype IS NULL)');
    }

    if (startDate && endDate) {
      conditions.push('DATE(th.tdate) BETWEEN ? AND ?');
      params.push(startDate, endDate);
    } else if (startDate) {
      conditions.push('DATE(th.tdate) >= ?');
      params.push(startDate);
    } else if (endDate) {
      conditions.push('DATE(th.tdate) <= ?');
      params.push(endDate);
    }

    const resolvedUsertypeExpr = 'COALESCE(u.type, th.utype)';
    if (
      usertypeId !== undefined &&
      usertypeId !== null &&
      `${usertypeId}` !== '' &&
      `${usertypeId}` !== 'all'
    ) {
      conditions.push(`${resolvedUsertypeExpr} = ?`);
      params.push(usertypeId);
    }

    if (tapstate && `${tapstate}` !== 'all') {
      conditions.push('th.tapstate = ?');
      params.push(tapstate);
    }

    const hasDateFilter = Boolean(startDate || endDate);
    const parsedLimit = Number.parseInt(limit, 10);
    const resolvedLimit = Number.isNaN(parsedLimit)
      ? null
      : Math.min(Math.max(parsedLimit, 1), 10000);
    const appliedLimit = resolvedLimit ?? (hasDateFilter ? null : 5000);
    const limitClause = appliedLimit ? 'LIMIT ?' : '';
    if (appliedLimit) {
      params.push(appliedLimit);
    }

    const selectParts = [
      'th.id',
      "DATE_FORMAT(th.tdate, '%Y-%m-%d') as tdate",
      'th.ttime',
      'th.tapstate',
      availableColumns.has('timeshift') ? 'th.timeshift' : 'NULL as timeshift',
      'th.studid as person_id',
      'th.utype as raw_utype',
      `${resolvedUsertypeExpr} as usertype_id`,
      'ut.utype as usertype_name',
      availableColumns.has('tapstatus') ? 'th.tapstatus' : 'NULL as tapstatus',
      availableColumns.has('station_id') ? 'th.station_id' : 'NULL as station_id',
      availableColumns.has('mode') ? 'th.mode' : 'NULL as mode',
      'th.createddatetime',
      availableColumns.has('updated_at') ? 'th.updated_at' : 'NULL as updated_at',
      `CASE
         WHEN th.utype = 7 THEN TRIM(CONCAT_WS(' ', s.lastname, s.firstname, s.middlename))
         ELSE TRIM(CONCAT_WS(' ', t.lastname, t.firstname, t.middlename))
       END AS full_name`,
      `CASE
         WHEN th.utype = 7 THEN s.sid
         ELSE t.tid
       END AS identifier`,
      `CASE
         WHEN th.utype = 7 THEN 'student'
         ELSE 'employee'
       END AS person_type`,
    ];

    const [rows] = await connection.query(
      `
      SELECT
        ${selectParts.join(',\n        ')}
      FROM taphistory th
      LEFT JOIN teacher t ON t.id = th.studid AND (th.utype <> 7 OR th.utype IS NULL)
      LEFT JOIN users u ON u.id = t.userid
      LEFT JOIN usertype ut ON ut.id = ${resolvedUsertypeExpr}
      LEFT JOIN studinfo s ON s.id = th.studid AND th.utype = 7
      WHERE ${conditions.join(' AND ')}
      ORDER BY th.tdate DESC, th.ttime DESC, th.id DESC
      ${limitClause}
    `,
      params
    );

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: rows,
      meta: {
        limit: appliedLimit,
      },
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching employee attendance:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch employee attendance',
      error: error.message,
    });
  }
};
