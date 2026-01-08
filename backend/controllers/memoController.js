import mysql from 'mysql2/promise';
import db from '../config/db.js';

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
 * Get all memos from school database
 */
export const getAllMemos = async (req, res) => {
  let connection = null;

  try {
    const { schoolDbConfig } = req.body;
    const userId = req.user.id;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    // Get all memos
    const [memos] = await connection.query(`
      SELECT
        id,
        title,
        content,
        createdby as creator_name,
        created_at,
        updated_at,
        user_ids
      FROM memorandums
      ORDER BY created_at DESC
    `);

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: memos,
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching memos:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch memos',
      error: error.message,
    });
  }
};

/**
 * Get single memo by ID from school database
 */
export const getMemoById = async (req, res) => {
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

    // Get memo
    const [memos] = await connection.query(`
      SELECT
        id,
        title,
        content,
        createdby as creator_name,
        created_at,
        updated_at,
        user_ids
      FROM memorandums
      WHERE id = ?
    `, [id]);

    if (memos.length === 0) {
      await connection.end();
      return res.status(404).json({
        status: 'error',
        message: 'Memo not found',
      });
    }

    await connection.end();

    res.status(200).json({
      status: 'success',
      data: memos[0],
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error fetching memo:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch memo',
      error: error.message,
    });
  }
};

/**
 * Create new memo in school database
 */
export const createMemo = async (req, res) => {
  let connection = null;

  try {
    const { schoolDbConfig, title, content, user_ids } = req.body;
    const userId = req.user.id;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    if (!title || !content) {
      return res.status(400).json({
        status: 'error',
        message: 'Title and content are required',
      });
    }

    // Get user's username from main database
    const [users] = await db.query(
      'SELECT username FROM users WHERE id = ?',
      [userId]
    );

    if (users.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      });
    }

    const createdby = users[0].username;

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    // Prepare user_ids (null for ALL, or JSON array for specific users)
    const userIdsJson = user_ids && user_ids.length > 0 ? JSON.stringify(user_ids) : null;

    // Insert memo
    const [result] = await connection.query(
      `INSERT INTO memorandums (title, content, createdby, user_ids, created_at, updated_at)
       VALUES (?, ?, ?, ?, NOW(), NOW())`,
      [title, content, createdby, userIdsJson]
    );

    await connection.end();

    res.status(201).json({
      status: 'success',
      message: 'Memo created successfully',
      data: {
        id: result.insertId,
        title,
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

    console.error('Error creating memo:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to create memo',
      error: error.message,
    });
  }
};

/**
 * Update memo in school database
 */
export const updateMemo = async (req, res) => {
  let connection = null;

  try {
    const { id } = req.params;
    const { schoolDbConfig, title, content, user_ids } = req.body;

    if (!schoolDbConfig || !schoolDbConfig.db_name || !schoolDbConfig.db_username) {
      return res.status(400).json({
        status: 'error',
        message: 'School database configuration is required',
      });
    }

    // Connect to school database
    connection = await getSchoolConnection(schoolDbConfig);

    // Check if memo exists
    const [existingMemo] = await connection.query(
      'SELECT id FROM memorandums WHERE id = ?',
      [id]
    );

    if (existingMemo.length === 0) {
      await connection.end();
      return res.status(404).json({
        status: 'error',
        message: 'Memo not found',
      });
    }

    // Prepare user_ids
    const userIdsJson = user_ids && user_ids.length > 0 ? JSON.stringify(user_ids) : null;

    // Update memo
    await connection.query(
      `UPDATE memorandums
       SET title = ?, content = ?, user_ids = ?, updated_at = NOW()
       WHERE id = ?`,
      [title, content, userIdsJson, id]
    );

    await connection.end();

    res.status(200).json({
      status: 'success',
      message: 'Memo updated successfully',
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error updating memo:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to update memo',
      error: error.message,
    });
  }
};

/**
 * Delete memo from school database
 */
export const deleteMemo = async (req, res) => {
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

    // Check if memo exists
    const [existingMemo] = await connection.query(
      'SELECT id FROM memorandums WHERE id = ?',
      [id]
    );

    if (existingMemo.length === 0) {
      await connection.end();
      return res.status(404).json({
        status: 'error',
        message: 'Memo not found',
      });
    }

    // Delete memo
    await connection.query('DELETE FROM memorandums WHERE id = ?', [id]);

    await connection.end();

    res.status(200).json({
      status: 'success',
      message: 'Memo deleted successfully',
    });
  } catch (error) {
    if (connection) {
      try {
        await connection.end();
      } catch (closeError) {
        console.error('Error closing connection:', closeError);
      }
    }

    console.error('Error deleting memo:', error);
    res.status(500).json({
      status: 'error',
      message: 'Failed to delete memo',
      error: error.message,
    });
  }
};
