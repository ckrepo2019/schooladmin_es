import jwt from 'jsonwebtoken';

/**
 * Middleware to verify JWT token
 */
export const verifyToken = (req, res, next) => {
  try {
    // Get token from Authorization header (preferred) or cookie
    const headerToken = req.headers.authorization?.split(' ')[1];
    const cookieToken = req.cookies.token;
    const token = headerToken || cookieToken;

    if (!token) {
      return res.status(401).json({
        status: 'error',
        message: 'Access denied. No token provided.',
      });
    }

    // Verify token
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded;
    next();
  } catch (error) {
    return res.status(401).json({
      status: 'error',
      message: 'Invalid or expired token',
    });
  }
};

/**
 * Middleware to verify super-admin role
 */
export const verifySuperAdmin = (req, res, next) => {
  if (req.user.role !== 'super-admin') {
    return res.status(403).json({
      status: 'error',
      message: 'Access denied. Super admin privileges required.',
    });
  }
  next();
};

/**
 * Middleware to verify admin role (admin or super-admin)
 */
export const verifyAdmin = (req, res, next) => {
  if (req.user.role !== 'admin' && req.user.role !== 'super-admin') {
    return res.status(403).json({
      status: 'error',
      message: 'Access denied. Admin privileges required.',
    });
  }
  next();
};
