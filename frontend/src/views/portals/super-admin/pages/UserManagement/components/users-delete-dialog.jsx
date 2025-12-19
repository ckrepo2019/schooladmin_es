import { useState } from 'react'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useUsers } from './users-provider'

export function UsersDeleteDialog({ onSuccess }) {
  const { open, setOpen, currentRow } = useUsers()
  const [confirmText, setConfirmText] = useState('')
  const [isDeleting, setIsDeleting] = useState(false)

  const isOpen = open === 'delete'

  const handleDelete = async () => {
    if (confirmText !== currentRow?.username) {
      toast.error('Username does not match')
      return
    }

    setIsDeleting(true)

    try {
      const token = localStorage.getItem('token')
      const response = await fetch(`http://localhost:5000/api/super-admin/users/${currentRow.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
      })

      const data = await response.json()

      if (response.ok) {
        toast.success(data.message || `User "${currentRow?.username}" deleted successfully`)
        setOpen(null)
        setConfirmText('')
        if (onSuccess) onSuccess()
      } else {
        toast.error(data.message || 'Failed to delete user')
      }
    } catch (error) {
      toast.error('An error occurred. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  const handleOpenChange = (newOpen) => {
    if (!newOpen && !isDeleting) {
      setOpen(null)
      setConfirmText('')
    }
  }

  return (
    <AlertDialog open={isOpen} onOpenChange={handleOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This action cannot be undone. This will permanently delete the user{' '}
            <span className='font-semibold'>{currentRow?.username}</span> and
            remove all associated data from the system.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div className='space-y-2'>
          <Label htmlFor='confirm-text'>
            Type <span className='font-semibold'>{currentRow?.username}</span>{' '}
            to confirm:
          </Label>
          <Input
            id='confirm-text'
            value={confirmText}
            onChange={(e) => setConfirmText(e.target.value)}
            placeholder={currentRow?.username}
            disabled={isDeleting}
          />
        </div>
        <AlertDialogFooter>
          <AlertDialogCancel onClick={() => setConfirmText('')} disabled={isDeleting}>
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            onClick={handleDelete}
            disabled={confirmText !== currentRow?.username || isDeleting}
            className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
          >
            {isDeleting ? (
              <>
                <Loader2 className='mr-2 h-4 w-4 animate-spin' />
                Deleting...
              </>
            ) : (
              'Delete'
            )}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
