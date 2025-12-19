import { Settings, Check } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useLayout } from '@/context/layout-provider'
import { useTheme } from '@/context/theme-provider'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { useSidebar } from './ui/sidebar'

// Simple visual preview components
const ThemePreview = ({ type, selected }) => (
  <div
    className={cn(
      'relative w-full aspect-video rounded-md border-2 overflow-hidden cursor-pointer transition-all',
      selected ? 'border-primary ring-2 ring-primary' : 'border-border hover:border-primary/50'
    )}
  >
    {selected && (
      <Check className='absolute top-1 right-1 h-4 w-4 text-primary bg-background rounded-full p-0.5' />
    )}
    <div className={cn('h-full flex', type === 'light' ? 'bg-white' : type === 'dark' ? 'bg-slate-900' : 'bg-gradient-to-r from-white to-slate-900')}>
      <div className={cn('w-1/3 p-2 space-y-1', type === 'light' ? 'bg-gray-100' : type === 'dark' ? 'bg-slate-800' : 'bg-gradient-to-r from-gray-100 to-slate-800')}>
        <div className={cn('h-1.5 w-8 rounded', type === 'light' ? 'bg-gray-300' : type === 'dark' ? 'bg-slate-600' : 'bg-gray-400')} />
        <div className={cn('h-1 w-6 rounded', type === 'light' ? 'bg-gray-200' : type === 'dark' ? 'bg-slate-700' : 'bg-gray-500')} />
      </div>
      <div className='flex-1 p-2 space-y-1'>
        <div className={cn('h-1 w-full rounded', type === 'light' ? 'bg-gray-200' : type === 'dark' ? 'bg-slate-700' : 'bg-gray-500')} />
        <div className={cn('h-8 w-full rounded', type === 'light' ? 'bg-gray-100' : type === 'dark' ? 'bg-slate-800' : 'bg-gray-600')} />
      </div>
    </div>
  </div>
)

const SidebarPreview = ({ type, selected }) => (
  <div
    className={cn(
      'relative w-full aspect-video rounded-md border-2 overflow-hidden cursor-pointer transition-all',
      selected ? 'border-primary ring-2 ring-primary' : 'border-border hover:border-primary/50'
    )}
  >
    {selected && (
      <Check className='absolute top-1 right-1 h-4 w-4 text-primary bg-background rounded-full p-0.5' />
    )}
    <div className='h-full flex bg-muted'>
      {type === 'inset' && (
        <>
          <div className='w-1/4 bg-sidebar border-r border-sidebar-border p-1'>
            <div className='h-1 w-full bg-sidebar-accent rounded mb-1' />
            <div className='h-0.5 w-3/4 bg-sidebar-accent/50 rounded' />
          </div>
          <div className='flex-1 p-2'>
            <div className='h-full bg-background rounded border' />
          </div>
        </>
      )}
      {type === 'floating' && (
        <>
          <div className='w-1/4 bg-sidebar rounded-r-lg m-1 p-1 shadow-lg'>
            <div className='h-1 w-full bg-sidebar-accent rounded mb-1' />
            <div className='h-0.5 w-3/4 bg-sidebar-accent/50 rounded' />
          </div>
          <div className='flex-1 p-2'>
            <div className='h-full bg-background/50' />
          </div>
        </>
      )}
      {type === 'sidebar' && (
        <>
          <div className='w-1/4 bg-sidebar p-1'>
            <div className='h-1 w-full bg-sidebar-accent rounded mb-1' />
            <div className='h-0.5 w-3/4 bg-sidebar-accent/50 rounded' />
          </div>
          <div className='flex-1 bg-background' />
        </>
      )}
    </div>
  </div>
)

const LayoutPreview = ({ type, selected }) => (
  <div
    className={cn(
      'relative w-full aspect-video rounded-md border-2 overflow-hidden cursor-pointer transition-all',
      selected ? 'border-primary ring-2 ring-primary' : 'border-border hover:border-primary/50'
    )}
  >
    {selected && (
      <Check className='absolute top-1 right-1 h-4 w-4 text-primary bg-background rounded-full p-0.5' />
    )}
    <div className='h-full flex bg-muted'>
      {type === 'default' && (
        <>
          <div className='w-1/3 bg-sidebar p-1.5 space-y-0.5'>
            <div className='h-1 w-full bg-sidebar-accent rounded' />
            <div className='h-0.5 w-3/4 bg-sidebar-accent/50 rounded' />
          </div>
          <div className='flex-1 bg-background' />
        </>
      )}
      {type === 'icon' && (
        <>
          <div className='w-8 bg-sidebar p-1 flex flex-col items-center gap-1'>
            <div className='h-1 w-4 bg-sidebar-accent rounded' />
            <div className='h-1 w-4 bg-sidebar-accent/50 rounded' />
          </div>
          <div className='flex-1 bg-background' />
        </>
      )}
      {type === 'offcanvas' && (
        <div className='w-full bg-background' />
      )}
    </div>
  </div>
)

export function ConfigDrawer() {
  const { open, setOpen } = useSidebar()
  const { resetTheme, theme, setTheme } = useTheme()
  const { resetLayout, collapsible, setCollapsible, variant, setVariant } = useLayout()
  const layoutMode = open ? 'default' : collapsible

  const handleReset = () => {
    setOpen(true)
    resetTheme()
    resetLayout()
  }

  return (
    <Sheet>
      <SheetTrigger asChild>
        <Button
          size='icon'
          variant='ghost'
          aria-label='Open theme settings'
          className='rounded-full'
        >
          <Settings />
        </Button>
      </SheetTrigger>
      <SheetContent className='flex flex-col w-80'>
        <SheetHeader className='pb-0 text-start'>
          <SheetTitle>Theme Settings</SheetTitle>
          <SheetDescription>
            Adjust the appearance and layout to suit your preferences.
          </SheetDescription>
        </SheetHeader>
        <div className='space-y-6 overflow-y-auto px-4 flex-1'>
          {/* Theme Selection */}
          <div>
            <h3 className='text-sm font-semibold mb-3'>Theme</h3>
            <div className='grid grid-cols-3 gap-2'>
              {['system', 'light', 'dark'].map((t) => (
                <div key={t} className='flex flex-col gap-1'>
                  <button onClick={() => setTheme(t)} className='focus:outline-none'>
                    <ThemePreview type={t} selected={theme === t} />
                  </button>
                  <span className='text-xs text-center capitalize text-muted-foreground'>
                    {t}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Sidebar Variant */}
          <div>
            <h3 className='text-sm font-semibold mb-3'>Sidebar</h3>
            <div className='grid grid-cols-3 gap-2'>
              {['inset', 'floating', 'sidebar'].map((v) => (
                <div key={v} className='flex flex-col gap-1'>
                  <button onClick={() => setVariant(v)} className='focus:outline-none'>
                    <SidebarPreview type={v} selected={variant === v} />
                  </button>
                  <span className='text-xs text-center capitalize text-muted-foreground'>
                    {v}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Layout Mode */}
          <div>
            <h3 className='text-sm font-semibold mb-3'>Layout</h3>
            <div className='grid grid-cols-3 gap-2'>
              {[
                { value: 'default', label: 'Default' },
                { value: 'icon', label: 'Compact' },
                { value: 'offcanvas', label: 'Full' }
              ].map((c) => (
                <div key={c.value} className='flex flex-col gap-1'>
                  <button
                    onClick={() => {
                      if (c.value === 'default') {
                        setOpen(true)
                        return
                      }
                      setOpen(false)
                      setCollapsible(c.value)
                    }}
                    className='focus:outline-none'
                  >
                    <LayoutPreview
                      type={c.value}
                      selected={layoutMode === c.value}
                    />
                  </button>
                  <span className='text-xs text-center text-muted-foreground'>
                    {c.label}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
        <SheetFooter className='gap-2'>
          <Button variant='destructive' onClick={handleReset} className='w-full'>
            Reset
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
