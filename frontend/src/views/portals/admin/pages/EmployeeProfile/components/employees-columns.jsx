import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { DataTableColumnHeader } from './data-table-column-header'

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
    accessorKey: 'name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Name' />
    ),
    cell: ({ row }) => {
      return (
        <div className='flex space-x-2'>
          <span className='max-w-[500px] truncate font-medium'>
            {row.getValue('name')}
          </span>
        </div>
      )
    },
  },
  {
    accessorKey: 'email',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='User ID' />
    ),
    cell: ({ row }) => (
      <div className='font-mono text-sm'>{row.getValue('email')}</div>
    ),
  },
  {
    accessorKey: 'usertype_name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='User Type' />
    ),
    cell: ({ row }) => {
      const usertype = row.getValue('usertype_name')
      const additionalTypes = row.original.additional_types || []

      return (
        <div className='flex flex-wrap gap-1'>
          {usertype && (
            <Badge variant='default' className='text-xs'>
              {usertype}
            </Badge>
          )}
          {additionalTypes.map((type, index) => (
            <Badge key={index} variant='secondary' className='text-xs'>
              {type.usertype_name}
            </Badge>
          ))}
        </div>
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
]
