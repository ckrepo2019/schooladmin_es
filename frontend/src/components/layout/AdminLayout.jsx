import { Outlet, useNavigate } from 'react-router-dom'
import { useEffect } from 'react'
import { Toaster } from 'sonner'
import { cn } from '@/lib/utils'
import { getCookie } from '@/lib/cookies'
import { ThemeProvider } from '@/context/theme-provider'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { SkipToMain } from '@/components/SkipToMain'
import { AppSidebar } from './AppSidebar'
import { Header } from './Header'
import { Main } from './Main'

export function AdminLayout() {
  const navigate = useNavigate()
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  const selectedSchool = JSON.parse(localStorage.getItem('selectedSchool') || 'null')
  const defaultOpen = getCookie('sidebar_state') !== 'false'

  // Check if school is selected, if not redirect to school selection
  useEffect(() => {
    if (!selectedSchool) {
      navigate('/admin/select-school')
    }
  }, [selectedSchool, navigate])

  return (
    <ThemeProvider>
      <LayoutProvider>
        <SearchProvider>
          <SidebarProvider defaultOpen={defaultOpen}>
            <SkipToMain />
            <AppSidebar user={user} selectedSchool={selectedSchool} />
            <SidebarInset
              className={cn(
                '@container/content',
                'has-data-[layout=fixed]:h-svh',
                'peer-data-[variant=inset]:has-data-[layout=fixed]:h-[calc(100svh-(var(--spacing)*4))]'
              )}
            >
              <Header>
                <div className='flex items-center gap-2'>
                  <h1 className='text-lg font-semibold'>
                    {selectedSchool?.school_name || 'Dashboard'}
                  </h1>
                </div>
              </Header>
              <Main id='content'>
                <Outlet />
              </Main>
            </SidebarInset>
            <Toaster richColors position='top-right' />
          </SidebarProvider>
        </SearchProvider>
      </LayoutProvider>
    </ThemeProvider>
  )
}
