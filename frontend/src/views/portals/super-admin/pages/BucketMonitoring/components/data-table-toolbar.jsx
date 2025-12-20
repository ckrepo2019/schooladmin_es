import { X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { DataTableFacetedFilter } from './data-table-faceted-filter'

export function DataTableToolbar({ table }) {
  const isFiltered = table.getState().columnFilters.length > 0

  // Get unique folders for filter options
  const folderColumn = table.getColumn('folder')
  const folders = folderColumn?.getFacetedUniqueValues()
  const folderOptions = Array.from(folders?.keys() || []).map((folder) => ({
    label: folder,
    value: folder,
  }))

  return (
    <div className='flex items-center justify-between'>
      <div className='flex flex-1 items-center space-x-2'>
        <Input
          placeholder='Search files...'
          value={(table.getColumn('filename')?.getFilterValue()) ?? ''}
          onChange={(event) =>
            table.getColumn('filename')?.setFilterValue(event.target.value)
          }
          className='h-8 w-[150px] lg:w-[250px]'
        />

        {folderColumn && folderOptions.length > 0 && (
          <DataTableFacetedFilter
            column={folderColumn}
            title='Folder'
            options={folderOptions}
          />
        )}

        {isFiltered && (
          <Button
            variant='ghost'
            onClick={() => table.resetColumnFilters()}
            className='h-8 px-2 lg:px-3'
          >
            Reset
            <X className='ml-2 h-4 w-4' />
          </Button>
        )}
      </div>
    </div>
  )
}
