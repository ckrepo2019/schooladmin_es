import mysql from 'mysql2/promise';

/**
 * Get school database connection
 */
const getSchoolConnection = async (schoolDbConfig) => {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: schoolDbConfig.db_username,
    password: schoolDbConfig.db_password || '',
    database: schoolDbConfig.db_name,
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

    // Get all employees with their user type
    const [employees] = await connection.query(`
      SELECT
        u.id,
        u.name,
        u.email,
        u.type as usertype_id,
        ut.utype as usertype_name,
        u.created_at,
        u.updated_at
      FROM users u
      LEFT JOIN usertype ut ON u.type = ut.id
      WHERE u.deleted = 0
      ORDER BY u.name ASC
    `);

    // Get additional user types from faspriv for each employee
    for (let employee of employees) {
      const [additionalTypes] = await connection.query(`
        SELECT
          fp.usertype,
          ut.utype as usertype_name
        FROM faspriv fp
        LEFT JOIN usertype ut ON fp.usertype = ut.id
        WHERE fp.userid = ? AND fp.deleted = 0
      `, [employee.id]);

      employee.additional_types = additionalTypes;
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

    // Get employee with user type
    const [employees] = await connection.query(`
      SELECT
        u.id,
        u.name,
        u.email,
        u.type as usertype_id,
        ut.utype as usertype_name,
        u.created_at,
        u.updated_at
      FROM users u
      LEFT JOIN usertype ut ON u.type = ut.id
      WHERE u.id = ? AND u.deleted = 0
    `, [id]);

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
    `, [id]);

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

    // Get all active user types
    const [userTypes] = await connection.query(`
      SELECT
        id,
        utype,
        departmentid,
        type_active,
        sortid
      FROM usertype
      WHERE deleted = 0 AND type_active = 1
      ORDER BY sortid ASC, utype ASC
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
