import bcrypt from 'bcrypt'
import jwt from 'jsonwebtoken'
import db from '../config/db.js'

// Helper function to generate user_id
const generateUserId = async () => {
  const now = new Date()
  const yearMonth = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}`

  // Get the last user_id for this month
  const [lastUser] = await db.query(
    'SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1',
    [`${yearMonth}%`]
  )

  let sequence = 1
  if (lastUser.length > 0) {
    const lastSequence = parseInt(lastUser[0].user_id.slice(-4))
    sequence = lastSequence + 1
  }

  return `${yearMonth}${String(sequence).padStart(4, '0')}`
}

// Get all users (exclude super-admin)
export const getAllUsers = async (req, res) => {
  try {
    const [users] = await db.query(
      `SELECT u.id, u.user_id, u.username, u.password_str, u.role, u.created_at, u.updated_at,
       GROUP_CONCAT(s.id) as school_ids,
       GROUP_CONCAT(s.school_name) as school_names
       FROM users u
       LEFT JOIN user_schools us ON u.id = us.user_id
       LEFT JOIN schools s ON us.school_id = s.id
       WHERE u.role != 'super-admin'
       GROUP BY u.id
       ORDER BY u.created_at DESC`
    )

    // Format the response
    const formattedUsers = users.map(user => ({
      ...user,
      schools: user.school_ids ? user.school_ids.split(',').map((id, index) => ({
        id: parseInt(id),
        school_name: user.school_names.split(',')[index]
      })) : []
    }))

    // Remove the concatenated fields
    formattedUsers.forEach(user => {
      delete user.school_ids
      delete user.school_names
    })

    res.status(200).json({
      status: 'success',
      data: formattedUsers,
    })
  } catch (error) {
    console.error('Error fetching users:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch users',
    })
  }
}

// Get single user by ID
export const getUserById = async (req, res) => {
  try {
    const { id } = req.params

    const [users] = await db.query(
      `SELECT u.id, u.user_id, u.username, u.role, u.created_at, u.updated_at
       FROM users u
       WHERE u.id = ? AND u.role != 'super-admin'`,
      [id]
    )

    if (users.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      })
    }

    // Get user's schools
    const [schools] = await db.query(
      `SELECT s.id, s.school_name
       FROM schools s
       INNER JOIN user_schools us ON s.id = us.school_id
       WHERE us.user_id = ?`,
      [id]
    )

    res.status(200).json({
      status: 'success',
      data: {
        ...users[0],
        schools,
      },
    })
  } catch (error) {
    console.error('Error fetching user:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch user',
    })
  }
}

// Create new user
export const createUser = async (req, res) => {
  try {
    const { username, school_ids } = req.body

    // Validate required fields
    if (!username) {
      return res.status(400).json({
        status: 'error',
        message: 'Username is required',
      })
    }

    // Check if username already exists
    const [existingUser] = await db.query(
      'SELECT id FROM users WHERE username = ?',
      [username]
    )

    if (existingUser.length > 0) {
      return res.status(400).json({
        status: 'error',
        message: 'Username already exists',
      })
    }

    // Generate user_id
    const user_id = await generateUserId()

    // Hash default password
    const defaultPassword = '12345678'
    const hashedPassword = await bcrypt.hash(defaultPassword, 10)

    // Insert new user
    const [result] = await db.query(
      `INSERT INTO users (user_id, username, password, password_str, role, created_at, updated_at)
       VALUES (?, ?, ?, ?, 'admin', NOW(), NOW())`,
      [user_id, username, hashedPassword, defaultPassword]
    )

    const userId = result.insertId

    // Insert user-school relationships
    if (school_ids && school_ids.length > 0) {
      const values = school_ids.map(school_id => [userId, school_id])
      await db.query(
        'INSERT INTO user_schools (user_id, school_id) VALUES ?',
        [values]
      )
    }

    res.status(201).json({
      status: 'success',
      message: 'User created successfully',
      data: {
        id: userId,
        user_id,
        username,
        password: defaultPassword,
      },
    })
  } catch (error) {
    console.error('Error creating user:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to create user',
    })
  }
}

// Update user
export const updateUser = async (req, res) => {
  try {
    const { id } = req.params
    const { username, school_ids } = req.body

    // Check if user exists and is not super-admin
    const [existingUser] = await db.query(
      'SELECT id, role FROM users WHERE id = ?',
      [id]
    )

    if (existingUser.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      })
    }

    if (existingUser[0].role === 'super-admin') {
      return res.status(403).json({
        status: 'error',
        message: 'Cannot update super-admin user',
      })
    }

    // Check if new username conflicts with other users
    if (username) {
      const [conflictingUser] = await db.query(
        'SELECT id FROM users WHERE username = ? AND id != ?',
        [username, id]
      )

      if (conflictingUser.length > 0) {
        return res.status(400).json({
          status: 'error',
          message: 'Username already exists',
        })
      }

      // Update username
      await db.query(
        'UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?',
        [username, id]
      )
    }

    // Update user-school relationships
    if (school_ids !== undefined) {
      // Delete existing relationships
      await db.query('DELETE FROM user_schools WHERE user_id = ?', [id])

      // Insert new relationships
      if (school_ids.length > 0) {
        const values = school_ids.map(school_id => [id, school_id])
        await db.query(
          'INSERT INTO user_schools (user_id, school_id) VALUES ?',
          [values]
        )
      }
    }

    res.status(200).json({
      status: 'success',
      message: 'User updated successfully',
    })
  } catch (error) {
    console.error('Error updating user:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to update user',
    })
  }
}

// Delete user
export const deleteUser = async (req, res) => {
  try {
    const { id } = req.params

    // Check if user exists and is not super-admin
    const [existingUser] = await db.query(
      'SELECT id, role FROM users WHERE id = ?',
      [id]
    )

    if (existingUser.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      })
    }

    if (existingUser[0].role === 'super-admin') {
      return res.status(403).json({
        status: 'error',
        message: 'Cannot delete super-admin user',
      })
    }

    // Delete user (CASCADE will delete user_schools entries)
    await db.query('DELETE FROM users WHERE id = ?', [id])

    res.status(200).json({
      status: 'success',
      message: 'User deleted successfully',
    })
  } catch (error) {
    console.error('Error deleting user:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to delete user',
    })
  }
}

// Update user password
export const updatePassword = async (req, res) => {
  try {
    const { id } = req.params
    const { new_password } = req.body

    // Validate required fields
    if (!new_password || new_password.length < 8) {
      return res.status(400).json({
        status: 'error',
        message: 'Password must be at least 8 characters',
      })
    }

    // Check if user exists and is not super-admin
    const [existingUser] = await db.query(
      'SELECT id, role FROM users WHERE id = ?',
      [id]
    )

    if (existingUser.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      })
    }

    if (existingUser[0].role === 'super-admin') {
      return res.status(403).json({
        status: 'error',
        message: 'Cannot update super-admin password',
      })
    }

    // Hash new password
    const hashedPassword = await bcrypt.hash(new_password, 10)

    // Update password
    await db.query(
      'UPDATE users SET password = ?, password_str = ?, updated_at = NOW() WHERE id = ?',
      [hashedPassword, new_password, id]
    )

    res.status(200).json({
      status: 'success',
      message: 'Password updated successfully',
    })
  } catch (error) {
    console.error('Error updating password:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to update password',
    })
  }
}

// Restore super-admin session cookie
export const restoreSuperAdminSession = async (req, res) => {
  try {
    const token = jwt.sign(
      {
        id: req.user.id,
        user_id: req.user.user_id,
        username: req.user.username,
        role: req.user.role,
      },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
    )

    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000,
    })

    res.status(200).json({
      status: 'success',
      message: 'Super-admin session restored',
      data: {
        token,
        user: {
          id: req.user.id,
          user_id: req.user.user_id,
          username: req.user.username,
          role: req.user.role,
        },
      },
    })
  } catch (error) {
    console.error('Error restoring super-admin session:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to restore super-admin session',
    })
  }
}

// Switch account (super-admin impersonation)
export const switchUserAccount = async (req, res) => {
  try {
    const { id } = req.params

    const [users] = await db.query(
      'SELECT * FROM users WHERE id = ? LIMIT 1',
      [id]
    )

    if (users.length === 0) {
      return res.status(404).json({
        status: 'error',
        message: 'User not found',
      })
    }

    const user = users[0]

    if (user.role === 'super-admin') {
      return res.status(403).json({
        status: 'error',
        message: 'Cannot switch to super-admin user',
      })
    }

    const token = jwt.sign(
      {
        id: user.id,
        user_id: user.user_id,
        username: user.username,
        role: user.role,
      },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
    )

    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000,
    })

    const { password, password_str, ...userData } = user

    res.status(200).json({
      status: 'success',
      message: 'Switched account successfully',
      data: {
        user: userData,
        token,
      },
    })
  } catch (error) {
    console.error('Error switching account:', error)
    res.status(500).json({
      status: 'error',
      message: 'Failed to switch account',
    })
  }
}
