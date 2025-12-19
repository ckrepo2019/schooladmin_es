import { useState } from 'react'
import {
  flexRender,
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Plus } from 'lucide-react'
import { DataTablePagination } from './data-table-pagination'
import { getColumns } from './memos-columns'
import { MemoViewDialog } from './memo-view-dialog'
import { MemoVisibilityDialog } from './memo-visibility-dialog'

export function MemosTable({ data, onAddMemo, onEditMemo, onRefresh, selectedSchool }) {
  const [rowSelection, setRowSelection] = useState({})
  const [columnVisibility, setColumnVisibility] = useState({})
  const [columnFilters, setColumnFilters] = useState([])
  const [sorting, setSorting] = useState([])
  const [viewDialogMemo, setViewDialogMemo] = useState(null)
  const [visibilityDialogMemo, setVisibilityDialogMemo] = useState(null)

  const handleViewMemo = (memo) => {
    setViewDialogMemo(memo)
  }

  const handleViewVisibility = (memo) => {
    setVisibilityDialogMemo(memo)
  }

  const columns = getColumns(handleViewMemo, onEditMemo, handleViewVisibility)

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      columnVisibility,
      rowSelection,
      columnFilters,
    },
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFacetedRowModel: getFacetedRowModel(),
    getFacetedUniqueValues: getFacetedUniqueValues(),
  })

  return (
    <>
      <div className='space-y-6'>
        <div className='flex items-center justify-between'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Memo Board</h2>
            <p className='text-muted-foreground'>
              Create and manage announcements for employees.
            </p>
          </div>
          <Button onClick={onAddMemo}>
            <Plus className='mr-2 h-4 w-4' />
            Add Memo
          </Button>
        </div>

        <div className='space-y-4'>
          <div className='flex items-center justify-between'>
            <Input
              placeholder='Search memos...'
              value={(table.getColumn('title')?.getFilterValue()) ?? ''}
              onChange={(event) =>
                table.getColumn('title')?.setFilterValue(event.target.value)
              }
              className='h-8 w-[150px] lg:w-[250px]'
            />
          </div>

          <div className='rounded-md border'>
            <Table>
              <TableHeader>
                {table.getHeaderGroups().map((headerGroup) => (
                  <TableRow key={headerGroup.id}>
                    {headerGroup.headers.map((header) => {
                      return (
                        <TableHead key={header.id} colSpan={header.colSpan}>
                          {header.isPlaceholder
                            ? null
                            : flexRender(
                                header.column.columnDef.header,
                                header.getContext()
                              )}
                        </TableHead>
                      )
                    })}
                  </TableRow>
                ))}
              </TableHeader>
              <TableBody>
                {table.getRowModel().rows?.length ? (
                  table.getRowModel().rows.map((row) => (
                    <TableRow
                      key={row.id}
                      data-state={row.getIsSelected() && 'selected'}
                    >
                      {row.getVisibleCells().map((cell) => (
                        <TableCell key={cell.id}>
                          {flexRender(
                            cell.column.columnDef.cell,
                            cell.getContext()
                          )}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell
                      colSpan={columns.length}
                      className='h-24 text-center'
                    >
                      No memos found.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
          <DataTablePagination table={table} />
        </div>
      </div>

      <MemoViewDialog
        memo={viewDialogMemo}
        open={!!viewDialogMemo}
        onClose={() => setViewDialogMemo(null)}
      />

      <MemoVisibilityDialog
        memo={visibilityDialogMemo}
        open={!!visibilityDialogMemo}
        onClose={() => setVisibilityDialogMemo(null)}
        selectedSchool={selectedSchool}
      />
    </>
  )
}
