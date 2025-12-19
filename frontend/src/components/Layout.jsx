import { Link, Outlet, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'

export default function Layout() {
  const location = useLocation()

  const navLinks = [
    { path: '/database-test', label: 'Database Test' },
    { path: '/login', label: 'Login' }
  ]

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <Link to="/" className="text-xl font-bold text-gray-900">
                School Admin
              </Link>
            </div>
            <div className="flex items-center space-x-4">
              {navLinks.map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                >
                  <Button
                    variant={location.pathname === link.path ? 'default' : 'ghost'}
                  >
                    {link.label}
                  </Button>
                </Link>
              ))}
            </div>
          </div>
        </div>
      </nav>
      <main>
        <Outlet />
      </main>
    </div>
  )
}
