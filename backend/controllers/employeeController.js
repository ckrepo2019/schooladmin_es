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

/**
 * Get all employees from school database
 */
export const getAllEmployees = async (req, res) => {
  let connection = null;

  try {
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    // Get all employees from teacher table with user type from users table
    const [employees] = await connection.query(`
      SELECT
        COALESCE(u.id, t.userid) as id,
        t.id as teacher_id,
        t.userid,
        t.lastname,
        t.firstname,
        t.middlename,
        TRIM(CONCAT_WS(' ', t.lastname, t.firstname, t.middlename)) as name,
        u.email,
        u.type as usertype_id,
        ut.utype as usertype_name,
        u.created_at,
        u.updated_at
      FROM teacher t
      LEFT JOIN users u ON u.id = t.userid
      LEFT JOIN usertype ut ON u.type = ut.id
      WHERE t.deleted = 0 AND (u.deleted = 0 OR u.deleted IS NULL)
      ORDER BY t.lastname ASC, t.firstname ASC, t.middlename ASC
    `);

    // Get additional user types from faspriv for all employees (by userid)
    const userIds = employees
      .map((employee) => employee.userid)
      .filter((value) => value !== null && value !== undefined);

    const additionalTypesByUserId = new Map();

    if (userIds.length > 0) {
      const [additionalTypeRows] = await connection.query(`
        SELECT
          fp.userid,
          fp.usertype,
          ut.utype as usertype_name
        FROM faspriv fp
        LEFT JOIN usertype ut ON fp.usertype = ut.id
        WHERE fp.deleted = 0 AND fp.userid IN (?)
      `, [userIds]);

      for (const row of additionalTypeRows) {
        const key = String(row.userid);
        if (!additionalTypesByUserId.has(key)) additionalTypesByUserId.set(key, []);
        additionalTypesByUserId.get(key).push({
          usertype: row.usertype,
          usertype_name: row.usertype_name,
        });
      }
    }

    for (const employee of employees) {
      employee.additional_types = additionalTypesByUserId.get(String(employee.userid)) || [];
    }

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: employees,
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching employees:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch employees',
      error: error.message,
    });
  }
};

/**
 * Get single employee by ID from school database
 */
export const getEmployeeById = async (req, res) => {
  let connection = null;

  try {
    const { id } = req.params;
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    // Get employee from teacher table with user type from users table
    const [employees] = await connection.query(`
      SELECT
        COALESCE(u.id, t.userid) as id,
        t.id as teacher_id,
        t.userid,
        t.lastname,
        t.firstname,
        t.middlename,
        TRIM(CONCAT_WS(' ', t.lastname, t.firstname, t.middlename)) as name,
        u.email,
        u.type as usertype_id,
        ut.utype as usertype_name,
        u.created_at,
        u.updated_at
      FROM teacher t
      LEFT JOIN users u ON u.id = t.userid
      LEFT JOIN usertype ut ON u.type = ut.id
      WHERE t.deleted = 0
        AND (u.deleted = 0 OR u.deleted IS NULL)
        AND (t.userid = ? OR t.id = ?)
      LIMIT 1
    `, [id, id]);

    if (employees.length === 0) {
      await connection.end();
      return res.status(404).json({
        status: 'error',
        message: 'Employee not found',
      });
    }

    const employee = employees[0];

    // Get additional user types from faspriv
    const [additionalTypes] = await connection.query(`
      SELECT
        fp.usertype,
        ut.utype as usertype_name
      FROM faspriv fp
      LEFT JOIN usertype ut ON fp.usertype = ut.id
      WHERE fp.userid = ? AND fp.deleted = 0
    `, [employee.userid]);

    employee.additional_types = additionalTypes;

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: employee,
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching employee:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch employee',
      error: error.message,
    });
  }
};

/**
 * Get all user types from school database
 */
export const getUserTypes = async (req, res) => {
  let connection = null;

  try {
    const { schoolDbConfig } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    const availableColumns = new Set();
    try {
      const [columnRows] = await connection.execute(
        `SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = 'usertype'`,
        [schoolDbConfig.db_name]
      );
      for (const row of columnRows || []) {
        if (row?.COLUMN_NAME) {
          availableColumns.add(row.COLUMN_NAME);
        }
      }
    } catch (schemaError) {
      console.warn('Unable to probe usertype columns:', schemaError?.message || schemaError);
    }

    const selectParts = [
      'id',
      'utype',
      availableColumns.has('departmentid') ? 'departmentid' : 'NULL as departmentid',
      availableColumns.has('type_active') ? 'type_active' : 'NULL as type_active',
      availableColumns.has('sortid') ? 'sortid' : 'NULL as sortid',
    ];

    const whereParts = [];
    if (availableColumns.has('deleted')) {
      whereParts.push('deleted = 0');
    }
    if (availableColumns.has('type_active')) {
      whereParts.push('type_active = 1');
    }
    const whereClause = whereParts.length ? `WHERE ${whereParts.join(' AND ')}` : '';
    const orderClause = availableColumns.has('sortid')
      ? 'ORDER BY sortid ASC, utype ASC'
      : 'ORDER BY utype ASC';

    // Get all active user types
    const [userTypes] = await connection.query(`
      SELECT
        ${selectParts.join(',\n        ')}
      FROM usertype
      ${whereClause}
      ${orderClause}
    `);

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: userTypes,
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching user types:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch user types',
      error: error.message,
    });
  }
};
