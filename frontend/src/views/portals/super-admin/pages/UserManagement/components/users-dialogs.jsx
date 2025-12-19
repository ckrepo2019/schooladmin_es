import { useUsers } from './users-provider'
import { UsersActionDialog } from './users-action-dialog'
import { UsersDeleteDialog } from './users-delete-dialog'
import { UsersUpdatePasswordDialog } from './users-update-password-dialog'

export function UsersDialogs({ onSuccess }) {
  const { open } = useUsers()

  return (
    <>
      <UsersActionDialog
        key='add-user'
        open={open === 'add'}
        onSuccess={onSuccess}
      />
      <UsersActionDialog
        key={`edit-user-${open === 'edit' ? 'open' : 'closed'}`}
        open={open === 'edit'}
        onSuccess={onSuccess}
      />
      <UsersUpdatePasswordDialog onSuccess={onSuccess} />
      <UsersDeleteDialog onSuccess={onSuccess} />
    </>
  )
}
