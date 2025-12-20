import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { DataTableColumnHeader } from './data-table-column-header'
import { DataTableRowActions } from './data-table-row-actions'

// Helper function to format file size
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Helper to get file type from filename
const getFileType = (filename) => {
  const ext = filename.split('.').pop()?.toLowerCase()
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
  return imageExts.includes(ext) ? 'image' : ext
}

export const columns = [
  {
    id: 'select',
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && 'indeterminate')
        }
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label='Select all'
        className='translate-y-0.5'
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label='Select row'
        className='translate-y-0.5'
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'filename',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Filename' />
    ),
    cell: ({ row }) => {
      const type = getFileType(row.getValue('filename'))
      return (
        <div className='flex items-center space-x-2'>
          <span className='max-w-[400px] truncate font-medium'>
            {row.getValue('filename')}
          </span>
          {type === 'image' && (
            <Badge variant='secondary' className='text-xs'>
              Image
            </Badge>
          )}
        </div>
      )
    },
  },
  {
    accessorKey: 'folder',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Folder' />
    ),
    cell: ({ row }) => {
      return (
        <Badge variant='outline' className='text-xs'>
          {row.getValue('folder')}
        </Badge>
      )
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
  },
  {
    accessorKey: 'size',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Size' />
    ),
    cell: ({ row }) => {
      return (
        <div className='text-sm text-muted-foreground'>
          {formatFileSize(row.getValue('size'))}
        </div>
      )
    },
  },
  {
    accessorKey: 'lastModified',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Upload Date' />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('lastModified'))
      return (
        <div className='text-sm text-muted-foreground'>
          {date.toLocaleDateString()} {date.toLocaleTimeString()}
        </div>
      )
    },
  },
  {
    id: 'actions',
    cell: ({ row }) => <DataTableRowActions row={row} />,
  },
]
