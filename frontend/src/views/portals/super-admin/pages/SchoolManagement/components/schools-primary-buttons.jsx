import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useSchools } from './schools-provider'

export function SchoolsPrimaryButtons() {
  const { setOpen } = useSchools()

  return (
    <div className='flex items-center gap-2'>
      <Button onClick={() => setOpen('add')}>
        <Plus className='mr-2 h-4 w-4' />
        Add School
      </Button>
    </div>
  )
}
