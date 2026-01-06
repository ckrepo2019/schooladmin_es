import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { User, Lock, LogOut, Eye, EyeOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:5000'

export default function SettingsAccount() {
  const navigate = useNavigate()
  const [user, setUser] = useState(() => JSON.parse(localStorage.getItem('user') || '{}'))

  // Change Username State
  const [usernameForm, setUsernameForm] = useState({
    newUsername: '',
    currentPassword: '',
  })
  const [isChangingUsername, setIsChangingUsername] = useState(false)

  // Change Password State
  const [passwordForm, setPasswordForm] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  })
  const [isChangingPassword, setIsChangingPassword] = useState(false)
  const [showPasswords, setShowPasswords] = useState({
    current: false,
    new: false,
    confirm: false,
  })

  const handleLogout = () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('selectedSchool')
    toast.success('Signed out')
    navigate('/login')
  }

  const handleChangeUsername = async (e) => {
    e.preventDefault()

    if (!usernameForm.newUsername || !usernameForm.currentPassword) {
      toast.error('Please fill in all fields')
      return
    }

    if (usernameForm.newUsername === user.username) {
      toast.error('New username must be different from current username')
      return
    }

    setIsChangingUsername(true)

    try {
      const token = localStorage.getItem('token')
      const response = await fetch(`${API_BASE_URL}/api/auth/change-username`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          newUsername: usernameForm.newUsername,
          currentPassword: usernameForm.currentPassword,
        }),
      })

      const data = await response.json()

      if (response.ok) {
        // Update user in localStorage
        const updatedUser = { ...user, username: data.data.username }
        localStorage.setItem('user', JSON.stringify(updatedUser))
        setUser(updatedUser)

        toast.success('Username updated successfully')
        setUsernameForm({ newUsername: '', currentPassword: '' })
      } else {
        toast.error(data.message || 'Failed to update username')
      }
    } catch (error) {
      console.error('Error changing username:', error)
      toast.error('An error occurred. Please try again.')
    } finally {
      setIsChangingUsername(false)
    }
  }

  const handleChangePassword = async (e) => {
    e.preventDefault()

    if (!passwordForm.currentPassword || !passwordForm.newPassword || !passwordForm.confirmPassword) {
      toast.error('Please fill in all fields')
      return
    }

    if (passwordForm.newPassword.length < 6) {
      toast.error('New password must be at least 6 characters long')
      return
    }

    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      toast.error('New passwords do not match')
      return
    }

    setIsChangingPassword(true)

    try {
      const token = localStorage.getItem('token')
      const response = await fetch(`${API_BASE_URL}/api/auth/change-password`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          currentPassword: passwordForm.currentPassword,
          newPassword: passwordForm.newPassword,
        }),
      })

      const data = await response.json()

      if (response.ok) {
        toast.success('Password updated successfully')
        setPasswordForm({ currentPassword: '', newPassword: '', confirmPassword: '' })
      } else {
        toast.error(data.message || 'Failed to update password')
      }
    } catch (error) {
      console.error('Error changing password:', error)
      toast.error('An error occurred. Please try again.')
    } finally {
      setIsChangingPassword(false)
    }
  }

  return (
    <div className='space-y-6'>
      {/* Current Account Info */}
      <Card data-watermark='ACCOUNT'>
        <CardHeader>
          <CardTitle>Account Information</CardTitle>
          <CardDescription>Your current account details</CardDescription>
        </CardHeader>
        <CardContent className='space-y-4'>
          <div className='flex items-center gap-3 rounded-lg border bg-muted/30 p-4'>
            <User className='h-5 w-5 text-muted-foreground' />
            <div>
              <div className='text-sm font-medium'>Username</div>
              <div className='text-sm text-muted-foreground'>{user?.username || '-'}</div>
            </div>
          </div>
          <div className='flex items-center gap-3 rounded-lg border bg-muted/30 p-4'>
            <User className='h-5 w-5 text-muted-foreground' />
            <div>
              <div className='text-sm font-medium'>User ID</div>
              <div className='text-sm text-muted-foreground'>{user?.user_id || '-'}</div>
            </div>
          </div>
          <div className='flex items-center gap-3 rounded-lg border bg-muted/30 p-4'>
            <User className='h-5 w-5 text-muted-foreground' />
            <div>
              <div className='text-sm font-medium'>Role</div>
              <div className='text-sm text-muted-foreground capitalize'>{user?.role || '-'}</div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Change Username */}
      <Card data-watermark='USER'>
        <CardHeader>
          <div className='flex items-center gap-2'>
            <User className='h-5 w-5 text-primary' />
            <CardTitle>Change Username</CardTitle>
          </div>
          <CardDescription>Update your account username</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleChangeUsername} className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='newUsername'>New Username</Label>
              <Input
                id='newUsername'
                type='text'
                placeholder='Enter new username'
                value={usernameForm.newUsername}
                onChange={(e) =>
                  setUsernameForm({ ...usernameForm, newUsername: e.target.value })
                }
                disabled={isChangingUsername}
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='usernameCurrentPassword'>Current Password</Label>
              <Input
                id='usernameCurrentPassword'
                type='password'
                placeholder='Enter current password to confirm'
                value={usernameForm.currentPassword}
                onChange={(e) =>
                  setUsernameForm({ ...usernameForm, currentPassword: e.target.value })
                }
                disabled={isChangingUsername}
              />
            </div>
            <Button type='submit' disabled={isChangingUsername}>
              {isChangingUsername ? 'Updating...' : 'Update Username'}
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Change Password */}
      <Card data-watermark='PASS'>
        <CardHeader>
          <div className='flex items-center gap-2'>
            <Lock className='h-5 w-5 text-primary' />
            <CardTitle>Change Password</CardTitle>
          </div>
          <CardDescription>Update your account password</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleChangePassword} className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='passwordCurrent'>Current Password</Label>
              <div className='relative'>
                <Input
                  id='passwordCurrent'
                  type={showPasswords.current ? 'text' : 'password'}
                  placeholder='Enter current password'
                  value={passwordForm.currentPassword}
                  onChange={(e) =>
                    setPasswordForm({ ...passwordForm, currentPassword: e.target.value })
                  }
                  disabled={isChangingPassword}
                />
                <button
                  type='button'
                  onClick={() =>
                    setShowPasswords({ ...showPasswords, current: !showPasswords.current })
                  }
                  className='absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground'
                >
                  {showPasswords.current ? <EyeOff className='h-4 w-4' /> : <Eye className='h-4 w-4' />}
                </button>
              </div>
            </div>
            <div className='space-y-2'>
              <Label htmlFor='passwordNew'>New Password</Label>
              <div className='relative'>
                <Input
                  id='passwordNew'
                  type={showPasswords.new ? 'text' : 'password'}
                  placeholder='Enter new password (min. 6 characters)'
                  value={passwordForm.newPassword}
                  onChange={(e) =>
                    setPasswordForm({ ...passwordForm, newPassword: e.target.value })
                  }
                  disabled={isChangingPassword}
                />
                <button
                  type='button'
                  onClick={() =>
                    setShowPasswords({ ...showPasswords, new: !showPasswords.new })
                  }
                  className='absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground'
                >
                  {showPasswords.new ? <EyeOff className='h-4 w-4' /> : <Eye className='h-4 w-4' />}
                </button>
              </div>
            </div>
            <div className='space-y-2'>
              <Label htmlFor='passwordConfirm'>Confirm New Password</Label>
              <div className='relative'>
                <Input
                  id='passwordConfirm'
                  type={showPasswords.confirm ? 'text' : 'password'}
                  placeholder='Confirm new password'
                  value={passwordForm.confirmPassword}
                  onChange={(e) =>
                    setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })
                  }
                  disabled={isChangingPassword}
                />
                <button
                  type='button'
                  onClick={() =>
                    setShowPasswords({ ...showPasswords, confirm: !showPasswords.confirm })
                  }
                  className='absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground'
                >
                  {showPasswords.confirm ? <EyeOff className='h-4 w-4' /> : <Eye className='h-4 w-4' />}
                </button>
              </div>
            </div>
            <Button type='submit' disabled={isChangingPassword}>
              {isChangingPassword ? 'Updating...' : 'Update Password'}
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Sign Out */}
      <Card data-watermark='LOGOUT'>
        <CardHeader>
          <div className='flex items-center gap-2'>
            <LogOut className='h-5 w-5 text-destructive' />
            <CardTitle>Sign Out</CardTitle>
          </div>
          <CardDescription>Sign out from your account</CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant='destructive' onClick={handleLogout}>
            Sign Out
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
