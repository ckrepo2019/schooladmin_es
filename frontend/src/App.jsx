import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import Layout from '@/components/Layout'
import Login from '@/views/auth/login'
import DatabaseTest from '@/components/DatabaseTest'
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout'
import SuperAdminDashboard from '@/views/portals/super-admin/pages/Dashboard'
import SchoolManagement from '@/views/portals/super-admin/pages/SchoolManagement'
import UserManagement from '@/views/portals/super-admin/pages/UserManagement'
import BucketMonitoring from '@/views/portals/super-admin/pages/BucketMonitoring'
import { AdminLayout } from '@/components/layout/AdminLayout'
import SchoolSelection from '@/views/portals/admin/SchoolSelection'
import AdminDashboard from '@/views/portals/admin/pages/Dashboard'
import EmployeeProfile from '@/views/portals/admin/pages/EmployeeProfile'
import MemoBoard from '@/views/portals/admin/pages/MemoBoard'
import AdminSettings from '@/views/portals/admin/pages/Settings'
import SettingsProfile from '@/views/portals/admin/pages/Settings/Profile'
import SettingsAccount from '@/views/portals/admin/pages/Settings/Account'
import HelpCenter from '@/views/portals/admin/pages/HelpCenter'

// Protected Route Component
function ProtectedRoute({ children, requiredRole }) {
  const token = localStorage.getItem('token')
  const user = JSON.parse(localStorage.getItem('user') || '{}')

  if (!token) {
    return <Navigate to="/login" replace />
  }

  if (requiredRole && user.role !== requiredRole) {
    return <Navigate to="/login" replace />
  }

  return children
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Navigate to="/login" replace />} />
        <Route path="/login" element={<Login />} />

        {/* Super Admin Routes */}
        <Route
          path="/super-admin"
          element={
            <ProtectedRoute requiredRole="super-admin">
              <SuperAdminLayout />
            </ProtectedRoute>
          }
        >
          <Route path="dashboard" element={<SuperAdminDashboard />} />
          <Route path="users" element={<UserManagement />} />
          <Route path="schools" element={<SchoolManagement />} />
          <Route path="bucket" element={<BucketMonitoring />} />
        </Route>

        {/* Admin School Selection */}
        <Route
          path="/admin/select-school"
          element={
            <ProtectedRoute requiredRole="admin">
              <SchoolSelection />
            </ProtectedRoute>
          }
        />

        {/* Admin Routes */}
        <Route
          path="/admin"
          element={
            <ProtectedRoute requiredRole="admin">
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route path="dashboard" element={<AdminDashboard />} />
          <Route path="profile" element={<div className="p-6">Profile - Coming Soon</div>} />
          <Route path="calendar" element={<div className="p-6">Calendar - Coming Soon</div>} />
          <Route path="memo-board" element={<MemoBoard />} />
          <Route path="settings" element={<AdminSettings />}>
            <Route index element={<Navigate to="profile" replace />} />
            <Route path="profile" element={<SettingsProfile />} />
            <Route path="account" element={<SettingsAccount />} />
          </Route>
          <Route path="help-center" element={<HelpCenter />} />

          {/* Finance Reports */}
          <Route path="finance/cashier-transactions" element={<div className="p-6">Cashier Transactions - Coming Soon</div>} />
          <Route path="finance/daily-cash-progress" element={<div className="p-6">Daily Cash Progress Report - Coming Soon</div>} />
          <Route path="finance/monthly-summary" element={<div className="p-6">Monthly Summary - Coming Soon</div>} />
          <Route path="finance/yearly-summary" element={<div className="p-6">Yearly Summary - Coming Soon</div>} />
          <Route path="finance/account-receivables" element={<div className="p-6">Account Receivables - Coming Soon</div>} />
          <Route path="finance/expenses-monitoring" element={<div className="p-6">Expenses Monitoring - Coming Soon</div>} />

          {/* HR Reports */}
          <Route path="hr/employee-profile" element={<EmployeeProfile />} />
          <Route path="hr/employee-attendance" element={<div className="p-6">Employee Attendance - Coming Soon</div>} />

          {/* Registrar Reports */}
          <Route path="registrar/enrollment-summary" element={<div className="p-6">Enrollment Summary - Coming Soon</div>} />
        </Route>

        {/* Old Routes */}
        <Route element={<Layout />}>
          <Route path="/database-test" element={<DatabaseTest />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}

export default App
