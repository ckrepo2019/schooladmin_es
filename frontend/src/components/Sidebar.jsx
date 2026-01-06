import { useState } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { useDialogs } from '@/context/dialogs-provider'

export default function Sidebar({ className, collapsed, onToggle }) {
  const [open, setOpen] = useState(!collapsed)
  const { alert: openAlert } = useDialogs()

  const handleToggle = () => {
    setOpen(!open)
    onToggle && onToggle(!open)
  }

  return (
    <aside
      className={cn(
        'bg-background border-r border-border h-full flex flex-col transition-all duration-300 ease-in-out',
        open ? 'w-64' : 'w-16',
        className
      )}
      aria-expanded={open}
    >
      <div className="flex items-center justify-between p-3 border-b border-border">
        <div className="flex items-center gap-3">
          <div className={cn('rounded-md bg-indigo-600 text-white flex items-center justify-center', open ? 'w-10 h-10' : 'w-8 h-8')}>
            SA
          </div>
          {open && <div className="font-semibold">School Admin</div>}
        </div>
        <button
          onClick={handleToggle}
          className="p-1 rounded hover:bg-accent"
          aria-label="Toggle sidebar"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className={cn('w-5 h-5 transition-transform', open ? '' : 'rotate-180')}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
          </svg>
        </button>
      </div>

      <nav className="flex-1 overflow-y-auto py-4">
        <ul className="space-y-1 p-2">
          {/* Placeholder items - user requested not to copy nav; keep function only */}
          <li>
            <Link to="/database-test" className="flex items-center gap-3 p-2 rounded hover:bg-accent/50">
              <span className="w-6 h-6 flex items-center justify-center text-indigo-600">D</span>
              {open && <span>Database</span>}
            </Link>
          </li>
          <li>
            <Link to="/login" className="flex items-center gap-3 p-2 rounded hover:bg-accent/50">
              <span className="w-6 h-6 flex items-center justify-center text-indigo-600">L</span>
              {open && <span>Login</span>}
            </Link>
          </li>
        </ul>
      </nav>

      <div className="p-3 border-t border-border">
        <Button
          variant="ghost"
          className="w-full"
          onClick={() =>
            openAlert({
              title: 'Profile',
              description: 'Coming soon.',
            })
          }
        >
          {open ? 'Profile' : 'P'}
        </Button>
      </div>
    </aside>
  )
}
