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

export const getSchoolCalendarEvents = async (req, res) => {
  try {
    const { schoolDbConfig, startDate, endDate } = req.body;

    if (!schoolDbConfig) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    if (!startDate || !endDate) {
      return res.status(400).json({
        status: 'error',
        message: 'Start and end dates are required',
      });
    }

    const db = await getSchoolConnection(schoolDbConfig);

    const query = `
      SELECT
        id,
        syid,
        title,
        venue,
        \`start\`,
        \`end\`,
        acadprogid,
        gradelevelid,
        courseid,
        collegeid,
        involve,
        type,
        isnoclass,
        allDay,
        holiday,
        holidaytype,
        withpay,
        stime,
        etime
      FROM school_calendar
      WHERE deleted = 0
        AND DATE(\`start\`) <= ?
        AND DATE(IFNULL(NULLIF(\`end\`, 0), \`start\`)) >= ?
      ORDER BY \`start\` ASC, stime ASC
    `;

    const [rows] = await db.execute(query, [endDate, startDate]);
    await db.end();

    res.status(200).json({
      status: 'success',
      data: rows || [],
    });
  } catch (error) {
    console.error('Error fetching calendar events:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch calendar events',
      error: error.message,
    });
  }
};
