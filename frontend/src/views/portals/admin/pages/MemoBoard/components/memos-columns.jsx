import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Eye, Edit } from 'lucide-react'
import { DataTableColumnHeader } from './data-table-column-header'

export const getColumns = (onViewMemo, onEditMemo, onViewVisibility) => [
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
    accessorKey: 'title',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Title' />
    ),
    cell: ({ row }) => {
      return (
        <div className='flex space-x-2'>
          <span className='max-w-[400px] truncate font-medium'>
            {row.getValue('title')}
          </span>
        </div>
      )
    },
  },
  {
    accessorKey: 'creator_name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Created By' />
    ),
    cell: ({ row }) => (
      <div className='text-sm'>{row.getValue('creator_name') || '-'}</div>
    ),
  },
  {
    accessorKey: 'user_ids',
    header: 'Visibility',
    cell: ({ row }) => {
      const memo = row.original
      const rawUserIds = row.getValue('user_ids')
      const userIds = (() => {
        if (Array.isArray(rawUserIds)) return rawUserIds
        if (typeof rawUserIds === 'string' && rawUserIds.trim()) {
          try {
            const parsed = JSON.parse(rawUserIds)
            return Array.isArray(parsed) ? parsed : []
          } catch {
            return []
          }
        }
        return []
      })()
      const isAllUsers = userIds.length === 0
      const label = isAllUsers ? 'All Employees' : `${userIds.length} Employees`

      if (!onViewVisibility) {
        return (
          <Badge variant={isAllUsers ? 'default' : 'secondary'} className='text-xs'>
            {label}
          </Badge>
        )
      }

      return (
        <Badge
          asChild
          variant={isAllUsers ? 'default' : 'secondary'}
          className='text-xs cursor-pointer select-none hover:opacity-90'
        >
          <button
            type='button'
            onClick={(e) => {
              e.stopPropagation()
              onViewVisibility(memo)
            }}
            title='View employees'
          >
            {label}
          </button>
        </Badge>
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Created' />
    ),
    cell: ({ row }) => {
      const date = row.getValue('created_at')
      if (!date) return '-'
      return (
        <div className='text-sm text-muted-foreground'>
          {new Date(date).toLocaleDateString()}
        </div>
      )
    },
  },
  {
    id: 'actions',
    cell: ({ row }) => {
      const memo = row.original
      return (
        <div className='flex items-center gap-2'>
          <Button
            variant='ghost'
            size='sm'
            onClick={(e) => {
              e.stopPropagation()
              onViewMemo(memo)
            }}
          >
            <Eye className='h-4 w-4' />
          </Button>
          <Button
            variant='ghost'
            size='sm'
            onClick={(e) => {
              e.stopPropagation()
              onEditMemo(memo)
            }}
          >
            <Edit className='h-4 w-4' />
          </Button>
        </div>
      )
    },
  },
]
