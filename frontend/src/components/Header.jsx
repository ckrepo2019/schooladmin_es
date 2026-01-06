import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useDialogs } from '@/context/dialogs-provider'

export default function Header({ onToggleSidebar }) {
  const [searchOpen, setSearchOpen] = useState(false)
  const { alert: openAlert } = useDialogs()

  return (
    <header className="bg-background border-b border-border">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center gap-4">
            <button
              onClick={onToggleSidebar}
              className="p-2 rounded-md hover:bg-accent"
              aria-label="Toggle sidebar"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div className="hidden sm:block">
              <h1 className="text-lg font-semibold">Dashboard</h1>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className={cn('relative transition-all', searchOpen ? 'w-64' : 'w-0 overflow-hidden')}>
              <input
                className={cn(
                  'border border-border rounded-md bg-background text-foreground placeholder:text-muted-foreground px-3 py-2 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 transition-all',
                  searchOpen ? 'opacity-100' : 'opacity-0'
                )}
                placeholder="Search..."
              />
            </div>
            <button
              onClick={() => setSearchOpen((s) => !s)}
              className="p-2 rounded hover:bg-accent"
              aria-label="Search"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-muted-foreground" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M12.9 14.32a7 7 0 111.414-1.414l4.387 4.386-1.414 1.415-4.387-4.387zM8 13a5 5 0 100-10 5 5 0 000 10z" clipRule="evenodd" />
              </svg>
            </button>

            <div className="flex items-center gap-3">
              <Button
                variant="ghost"
                onClick={() =>
                  openAlert({
                    title: 'Account',
                    description: 'Coming soon.',
                  })
                }
              >
                A
              </Button>
            </div>
          </div>
        </div>
      </div>
    </header>
  )
}
