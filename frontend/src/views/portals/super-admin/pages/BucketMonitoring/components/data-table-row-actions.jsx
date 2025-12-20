import { Eye, Download, MoreHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useBucket } from './bucket-provider'

export function DataTableRowActions({ row }) {
  const { setOpen, setCurrentFile } = useBucket()

  const handlePreview = () => {
    setCurrentFile(row.original)
    setOpen('preview')
  }

  const handleDownload = () => {
    // Open file URL in new tab to trigger download
    window.open(row.original.url, '_blank')
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant='ghost'
          className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
        >
          <MoreHorizontal className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-[160px]'>
        <DropdownMenuItem onClick={handlePreview}>
          <Eye className='mr-2 h-3.5 w-3.5 text-muted-foreground/70' />
          Preview
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleDownload}>
          <Download className='mr-2 h-3.5 w-3.5 text-muted-foreground/70' />
          Download
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
