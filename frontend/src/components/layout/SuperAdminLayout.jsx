import { Outlet } from 'react-router-dom'
import { useEffect } from 'react'
import { Toaster } from 'sonner'
import { cn } from '@/lib/utils'
import { getCookie } from '@/lib/cookies'
import ckLogo from '@/assets/ck-logo.png'
import { setFavicon } from '@/lib/metadata'
import { ThemeProvider } from '@/context/theme-provider'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { SkipToMain } from '@/components/SkipToMain'
import { AppSidebar } from './AppSidebar'
import { Header } from './Header'
import { Main } from './Main'

export function SuperAdminLayout() {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  const defaultOpen = getCookie('sidebar_state') !== 'false'
  const appTitle = import.meta.env.VITE_APP_TITLE || 'School Admin'

  useEffect(() => {
    document.title = `SYS ADMIN | ${appTitle.toUpperCase()}`
    setFavicon(ckLogo)
  }, [appTitle])

  return (
    <ThemeProvider>
      <LayoutProvider>
        <SearchProvider>
          <SidebarProvider defaultOpen={defaultOpen}>
            <SkipToMain />
            <AppSidebar user={user} />
            <SidebarInset
              className={cn(
                // Set content container, so we can use container queries
                '@container/content',

                // If layout is fixed, set the height to 100svh to prevent overflow
                'has-data-[layout=fixed]:h-svh',

                // If layout is fixed and sidebar is inset, set the height to 100svh - spacing (total margins)
                // to prevent overflow
                'peer-data-[variant=inset]:has-data-[layout=fixed]:h-[calc(100svh-(var(--spacing)*4))]'
              )}
            >
              <Header>
                <div className='flex items-center gap-2'>
                  <h1 className='text-lg font-semibold'>Dashboard</h1>
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
