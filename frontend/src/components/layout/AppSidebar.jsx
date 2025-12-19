import {
  LayoutDashboard,
  Users,
  School,
  User,
  Calendar,
  Megaphone,
  DollarSign,
  UsersRound,
  GraduationCap,
  ChevronRight,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import { useLayout } from '@/context/layout-provider'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarRail,
} from '@/components/ui/sidebar'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { NavUser } from './NavUser'
import Logo from '@/assets/react.svg'

// Navigation items based on user role
const getNavItems = (role) => {
  const superAdminItems = [
    {
      title: 'Dashboard',
      url: '/super-admin/dashboard',
      icon: LayoutDashboard,
    },
    {
      title: 'User Management',
      url: '/super-admin/users',
      icon: Users,
    },
    {
      title: 'School Management',
      url: '/super-admin/schools',
      icon: School,
    },
  ]

  const adminItems = [
    {
      title: 'School Dashboard',
      url: '/admin/dashboard',
      icon: LayoutDashboard,
    },
    {
      title: 'Profile',
      url: '/admin/profile',
      icon: User,
    },
    {
      title: 'Calendar',
      url: '/admin/calendar',
      icon: Calendar,
    },
    {
      title: 'Memo Board',
      url: '/admin/memo-board',
      icon: Megaphone,
    },
    {
      title: 'Finance Reports',
      icon: DollarSign,
      items: [
        { title: 'Cashier Transactions', url: '/admin/finance/cashier-transactions' },
        { title: 'Daily Cash Progress Report', url: '/admin/finance/daily-cash-progress' },
        { title: 'Monthly Summary', url: '/admin/finance/monthly-summary' },
        { title: 'Yearly Summary', url: '/admin/finance/yearly-summary' },
        { title: 'Account Receivables', url: '/admin/finance/account-receivables' },
        { title: 'Expenses Monitoring', url: '/admin/finance/expenses-monitoring' },
      ],
    },
    {
      title: 'HR Reports',
      icon: UsersRound,
      items: [
        { title: 'Employee Profile', url: '/admin/hr/employee-profile' },
        { title: 'Employee Attendance', url: '/admin/hr/employee-attendance' },
      ],
    },
    {
      title: 'Registrar Reports',
      icon: GraduationCap,
      items: [
        { title: 'Enrollment Summary', url: '/admin/registrar/enrollment-summary' },
      ],
    },
  ]

  return role === 'super-admin' ? superAdminItems : adminItems
}

export function AppSidebar({ user, selectedSchool }) {
  const navItems = getNavItems(user?.role)
  const { collapsible, variant } = useLayout()

  return (
    <Sidebar collapsible={collapsible} variant={variant}>
      <SidebarHeader>
        <div className='flex items-center gap-2 px-4 py-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2'>
          {user?.role === 'admin' && selectedSchool?.image_logo ? (
            <div className='h-8 w-8 flex items-center justify-center rounded-lg overflow-hidden bg-muted'>
              <img
                src={`http://localhost:5000${selectedSchool.image_logo}`}
                alt={selectedSchool.school_name}
                className='h-full w-full object-contain'
                onError={(e) => {
                  e.target.src = Logo
                }}
              />
            </div>
          ) : (
            <img src={Logo} alt='Logo' className='h-8 w-8' />
          )}
          <div className='flex flex-col group-data-[collapsible=icon]:hidden'>
            <span className='text-lg font-semibold'>
              {user?.role === 'admin' && selectedSchool?.abbrv
                ? selectedSchool.abbrv
                : 'School Admin'}
            </span>
          </div>
        </div>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              {navItems.map((item) => (
                item.items ? (
                  // Collapsible menu item with submenu
                  <Collapsible
                    key={item.title}
                    asChild
                    defaultOpen={false}
                    className='group/collapsible'
                  >
                    <SidebarMenuItem>
                      <CollapsibleTrigger asChild>
                        <SidebarMenuButton tooltip={item.title}>
                          {item.icon && <item.icon />}
                          <span>{item.title}</span>
                          <ChevronRight className='ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' />
                        </SidebarMenuButton>
                      </CollapsibleTrigger>
                      <CollapsibleContent>
                        <SidebarMenuSub>
                          {item.items.map((subItem) => (
                            <SidebarMenuSubItem key={subItem.title}>
                              <SidebarMenuSubButton asChild>
                                <Link to={subItem.url}>
                                  <span>{subItem.title}</span>
                                </Link>
                              </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                          ))}
                        </SidebarMenuSub>
                      </CollapsibleContent>
                    </SidebarMenuItem>
                  </Collapsible>
                ) : (
                  // Regular menu item
                  <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton asChild tooltip={item.title}>
                      <Link to={item.url}>
                        <item.icon />
                        <span className='group-data-[collapsible=icon]:hidden'>
                          {item.title}
                        </span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter>
        <NavUser user={user} selectedSchool={selectedSchool} />
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  )
}
