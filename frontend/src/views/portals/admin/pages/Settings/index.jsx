import { NavLink, Outlet } from 'react-router-dom'
import { User, KeyRound } from 'lucide-react'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'

export default function Settings() {
  return (
    <div className='space-y-6'>
      <div>
        <h2 className='text-2xl font-bold tracking-tight'>Settings</h2>
        <p className='text-muted-foreground'>
          Manage your profile and account settings.
        </p>
      </div>

      <Separator />

      <div className='grid gap-6 lg:grid-cols-[240px_1fr]'>
        <aside className='space-y-1'>
          <NavLink
            to='profile'
            className={({ isActive }) =>
              cn(
                buttonVariants({ variant: isActive ? 'secondary' : 'ghost' }),
                'w-full justify-start'
              )
            }
          >
            <User className='mr-2 h-4 w-4' />
            Profile
          </NavLink>
          <NavLink
            to='account'
            className={({ isActive }) =>
              cn(
                buttonVariants({ variant: isActive ? 'secondary' : 'ghost' }),
                'w-full justify-start'
              )
            }
          >
            <KeyRound className='mr-2 h-4 w-4' />
            Account
          </NavLink>
        </aside>

        <div className='min-w-0'>
          <Outlet />
        </div>
      </div>
    </div>
  )
}

