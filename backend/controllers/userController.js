import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import db from '../config/db.js';

/**
 * Generate user_id in format: YYYYMM + 4-digit auto-increment ID
 * Example: 2025120001
 */
export const generateUserId = async () => {
  try {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const prefix = `${year}${month}`;

    // Get the highest ID for current month
    const [rows] = await db.query(
      `SELECT user_id FROM users
       WHERE user_id LIKE ?
       ORDER BY user_id DESC
       LIMIT 1`,
      [`${prefix}%`]
    );

    let nextId = 1;
    if (rows.length > 0) {
      const lastUserId = rows[0].user_id;
      const lastIdNumber = parseInt(lastUserId.slice(-4));
      nextId = lastIdNumber + 1;
    }

    const paddedId = String(nextId).padStart(4, '0');
    return `${prefix}${paddedId}`;
  } catch (error) {
    throw new Error(`Error generating user_id: ${error.message}`);
  }
};

/**
 * Login function - validates username/email and password with bcrypt
 * Allows both super-admin and admin users
 */
export const login = async (req, res) => {
  try {
    const { username, password } = req.body;

    // Validate input
    if (!username || !password) {
      return res.status(400).json({
        status: 'error',
        message: 'Username and password are required'
      });
    }

    // Find user by username
    const [users] = await db.query(
      'SELECT * FROM users WHERE username = ? LIMIT 1',
      [username]
    );

    if (users.length === 0) {
      return res.status(401).json({
        status: 'error',
        message: 'Invalid credentials'
      });
    }

    const user = users[0];

    // Compare password with bcrypt
    const isPasswordValid = await bcrypt.compare(password, user.password);

    if (!isPasswordValid) {
      return res.status(401).json({
        status: 'error',
        message: 'Invalid credentials'
      });
    }

    // Generate JWT token
    const token = jwt.sign(
      {
        id: user.id,
        user_id: user.user_id,
        username: user.username,
        role: user.role,
      },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
    );

    // Set token as httpOnly cookie
    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
    });

    // Successful login - return user data (exclude password)
    const { password: _, password_str: __, ...userData } = user;

    return res.status(200).json({
      status: 'success',
      message: 'Login successful',
      data: {
        user: userData,
        token, // Also send token in response for localStorage
      }
    });
  } catch (error) {
    console.error('Login error:', error);
    return res.status(500).json({
      status: 'error',
      message: 'Internal server error',
      error: error.message
    });
  }
};

/**
 * Create new user (for future use)
 */
export const createUser = async (req, res) => {
  try {
    const { username, password, role } = req.body;

    // Validate input
    if (!username || !password || !role) {
      return res.status(400).json({
        status: 'error',
        message: 'Username, password, and role are required'
      });
    }

    // Generate user_id
    const userId = await generateUserId();

    // Hash password
    const saltRounds = 10;
    const hashedPassword = await bcrypt.hash(password, saltRounds);

    // Insert user
    const [result] = await db.query(
      `INSERT INTO users (user_id, username, password, password_str, role, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, NOW(), NOW())`,
      [userId, username, hashedPassword, password, role]
    );

    return res.status(201).json({
      status: 'success',
      message: 'User created successfully',
      data: {
        user_id: userId,
        username,
        role
      }
    });
  } catch (error) {
    console.error('Create user error:', error);
    return res.status(500).json({
      status: 'error',
      message: 'Internal server error',
      error: error.message
    });
  }
};
