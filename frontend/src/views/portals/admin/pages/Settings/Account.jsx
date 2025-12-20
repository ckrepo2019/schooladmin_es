import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

export default function SettingsAccount() {
  const navigate = useNavigate()
  const user = useMemo(() => JSON.parse(localStorage.getItem('user') || '{}'), [])

  const handleLogout = () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('selectedSchool')
    toast.success('Signed out')
    navigate('/login')
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Account</CardTitle>
        <CardDescription>Account actions and preferences.</CardDescription>
      </CardHeader>
      <CardContent className='space-y-4'>
        <div className='text-sm'>
          <div className='font-medium text-foreground'>Signed in as</div>
          <div className='text-muted-foreground'>{user?.username || '-'}</div>
        </div>

        <Separator />

        <div className='flex flex-wrap gap-2'>
          <Button variant='destructive' onClick={handleLogout}>
            Sign out
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}

