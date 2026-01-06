import { useMemo } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'

export default function SettingsProfile() {
  const user = useMemo(() => JSON.parse(localStorage.getItem('user') || '{}'), [])

  return (
    <Card data-watermark='PROFILE'>
      <CardHeader>
        <CardTitle>Profile</CardTitle>
        <CardDescription>Your basic account information.</CardDescription>
      </CardHeader>
      <CardContent className='space-y-4'>
        <div className='space-y-2'>
          <Label htmlFor='username'>Username</Label>
          <Input id='username' value={user?.username || ''} disabled />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='user-id'>User ID</Label>
          <Input id='user-id' value={user?.user_id || ''} disabled />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='role'>Role</Label>
          <Input id='role' value={user?.role || ''} disabled />
        </div>
      </CardContent>
    </Card>
  )
}
