import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useUsers } from './users-provider'

export function UsersPrimaryButtons() {
  const { setOpen } = useUsers()

  return (
    <div className='flex items-center gap-2'>
      <Button onClick={() => setOpen('add')}>
        <Plus className='mr-2 h-4 w-4' />
        Add User
      </Button>
    </div>
  )
}
