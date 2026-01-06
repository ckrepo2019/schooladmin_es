import { useNavigate } from 'react-router-dom'
import {
  ChevronsUpDown,
  LogOut,
  Settings as SettingsIcon,
  Building2,
} from 'lucide-react'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { useDialogs } from '@/context/dialogs-provider'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'

export function NavUser({ user, selectedSchool }) {
  const { isMobile } = useSidebar()
  const navigate = useNavigate()
  const { confirm: openConfirm } = useDialogs()

  const handleLogout = async () => {
    const confirmed = await openConfirm({
      title: 'Sign out?',
      description: 'You will be redirected to the login page.',
      confirmText: 'Sign out',
      cancelText: 'Cancel',
    })

    if (!confirmed) return

    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('selectedSchool')
    navigate('/login')
  }

  const handleSwitchSchool = async () => {
    const confirmed = await openConfirm({
      title: 'Switch school?',
      description: 'You will be taken back to the school selection page.',
      confirmText: 'Switch',
      cancelText: 'Cancel',
    })

    if (!confirmed) return

    localStorage.removeItem('selectedSchool')
    navigate('/admin/select-school')
  }

  const getUserInitials = (name) => {
    if (!name) return '?'
    const parts = name.split(' ')
    if (parts.length >= 2) {
      return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size='lg'
              className='data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
            >
              <Avatar className='h-8 w-8 rounded-lg'>
                <AvatarFallback className='rounded-lg'>
                  {getUserInitials(user?.username || 'User')}
                </AvatarFallback>
              </Avatar>
              <div className='grid flex-1 text-start text-sm leading-tight'>
                <span className='truncate font-semibold'>{user?.username || 'User'}</span>
                <span className='truncate text-xs'>{user?.user_id || ''}</span>
              </div>
              <ChevronsUpDown className='ms-auto size-4' />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className='w-56 rounded-lg'
            side={isMobile ? 'bottom' : 'right'}
            align='end'
            sideOffset={4}
          >
            <DropdownMenuLabel className='p-0 font-normal'>
              <div className='flex items-center gap-2 px-1 py-1.5 text-start text-sm'>
                <Avatar className='h-8 w-8 rounded-lg'>
                  <AvatarFallback className='rounded-lg'>
                    {getUserInitials(user?.username || 'User')}
                  </AvatarFallback>
                </Avatar>
                <div className='grid flex-1 text-start text-sm leading-tight'>
                  <span className='truncate font-semibold'>{user?.username || 'User'}</span>
                  <span className='truncate text-xs'>{user?.user_id || ''}</span>
                </div>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem>
              <SettingsIcon className='mr-2 h-4 w-4' />
              Settings
            </DropdownMenuItem>
            {user?.role === 'admin' && selectedSchool && (
              <>
                <DropdownMenuItem onClick={handleSwitchSchool}>
                  <Building2 className='mr-2 h-4 w-4' />
                  Switch School
                </DropdownMenuItem>
              </>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={handleLogout} className='text-destructive'>
              <LogOut className='mr-2 h-4 w-4' />
              Sign out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
