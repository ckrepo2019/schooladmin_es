import { useSchools } from './schools-provider'
import { SchoolsActionDialog } from './schools-action-dialog'
import { SchoolsDeleteDialog } from './schools-delete-dialog'

export function SchoolsDialogs({ onSuccess }) {
  const { open } = useSchools()

  return (
    <>
      <SchoolsActionDialog
        key='add-school'
        open={open === 'add'}
        onSuccess={onSuccess}
      />
      <SchoolsActionDialog
        key={`edit-school-${open === 'edit' ? 'open' : 'closed'}`}
        open={open === 'edit'}
        onSuccess={onSuccess}
      />
      <SchoolsDeleteDialog onSuccess={onSuccess} />
    </>
  )
}
