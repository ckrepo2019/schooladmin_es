import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Copy, Eye, EyeOff } from 'lucide-react'
import { toast } from 'sonner'
import { DataTableColumnHeader } from './data-table-column-header'
import { DataTableRowActions } from './data-table-row-actions'
import { useState } from 'react'

function PasswordCell({ password }) {
  const [showPassword, setShowPassword] = useState(false)

  const handleCopy = () => {
    navigator.clipboard.writeText(password)
    toast.success('Password copied to clipboard')
  }

  return (
    <div className='flex items-center gap-2'>
      <span className='max-w-[120px] truncate font-mono text-sm'>
        {showPassword ? password : '••••••••'}
      </span>
      <div className='flex gap-1'>
        <Button
          variant='ghost'
          size='icon'
          className='h-7 w-7'
          onClick={() => setShowPassword(!showPassword)}
        >
          {showPassword ? <EyeOff className='h-3.5 w-3.5' /> : <Eye className='h-3.5 w-3.5' />}
        </Button>
        <Button
          variant='ghost'
          size='icon'
          className='h-7 w-7'
          onClick={handleCopy}
        >
          <Copy className='h-3.5 w-3.5' />
        </Button>
      </div>
    </div>
  )
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
    accessorKey: 'user_id',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='User ID' />
    ),
    cell: ({ row }) => (
      <div className='font-medium'>{row.getValue('user_id')}</div>
    ),
  },
  {
    accessorKey: 'username',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Username' />
    ),
    cell: ({ row }) => {
      return (
        <div className='flex space-x-2'>
          <span className='max-w-[500px] truncate font-medium'>
            {row.getValue('username')}
          </span>
        </div>
      )
    },
  },
  {
    accessorKey: 'password_str',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Password' />
    ),
    cell: ({ row }) => {
      const password = row.getValue('password_str')
      return password ? <PasswordCell password={password} /> : <span className='text-muted-foreground text-sm'>-</span>
    },
    enableSorting: false,
  },
  {
    accessorKey: 'schools',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Assigned Schools' />
    ),
    cell: ({ row }) => {
      const schools = row.getValue('schools') || []

      if (schools.length === 0) {
        return <span className='text-sm text-muted-foreground'>No schools assigned</span>
      }

      return (
        <div className='flex flex-wrap gap-1'>
          {schools.map((school) => (
            <Badge key={school.id} variant='secondary' className='text-xs'>
              {school.school_name}
            </Badge>
          ))}
        </div>
      )
    },
    enableSorting: false,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Created' />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('created_at'))
      return (
        <div className='text-sm text-muted-foreground'>
          {date.toLocaleDateString()}
        </div>
      )
    },
  },
  {
    id: 'actions',
    cell: ({ row }) => <DataTableRowActions row={row} />,
  },
]
