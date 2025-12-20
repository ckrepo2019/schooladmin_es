import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Loader2, LogIn } from 'lucide-react'
import { cn } from '@/lib/utils'
import { apiUrl } from '@/lib/api'
import ckLogo from '@/assets/ck-logo.png'
import { setFavicon } from '@/lib/metadata'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { PasswordInput } from '@/components/PasswordInput'
import DashboardPreview from '@/assets/image.png'

const formSchema = z.object({
  username: z
    .string()
    .min(1, 'Please enter your username or user ID')
    .min(3, 'Username must be at least 3 characters long'),
  password: z
    .string()
    .min(1, 'Please enter your password')
    .min(7, 'Password must be at least 7 characters long'),
})

export default function Login() {
  const [isLoading, setIsLoading] = useState(false)
  const navigate = useNavigate()
  const appTitle = import.meta.env.VITE_APP_TITLE || 'School Admin'

  useEffect(() => {
    document.title = `ESSENTIEL | ${appTitle.toUpperCase()}`
    setFavicon(ckLogo)
  }, [appTitle])

  const form = useForm({
    resolver: zodResolver(formSchema),
    defaultValues: {
      username: '',
      password: '',
    },
  })

  async function onSubmit(data) {
    setIsLoading(true)

    try {
      const response = await fetch(apiUrl('/api/super-admin/login'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include', // Include cookies
        body: JSON.stringify(data),
      })

      const result = await response.json()

      if (response.ok && result.status === 'success') {
        // Store token and user data
        localStorage.setItem('token', result.data.token)
        localStorage.setItem('user', JSON.stringify(result.data.user))

        // Navigate to appropriate dashboard
        if (result.data.user.role === 'super-admin') {
          navigate('/super-admin/dashboard')
        } else {
          // For admin users, go to school selection first
          navigate('/admin/select-school')
        }
      } else {
        // Show error message
        alert(result.message || 'Login failed')
        setIsLoading(false)
      }
    } catch (error) {
      console.error('Login error:', error)
      alert('Failed to connect to server')
      setIsLoading(false)
    }
  }

  return (
    <div className='relative container grid h-svh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0'>
      <div className='lg:p-8'>
        <div className='mx-auto flex w-full flex-col justify-center space-y-2 py-8 sm:w-[480px] sm:p-8'>
          <div className='mb-4 flex items-center justify-center'>
            <img src={ckLogo} alt='Logo' className='me-2 h-8 w-8' />
            <h1 className='text-xl font-medium'>School Admin</h1>
          </div>
        </div>
        <div className='mx-auto flex w-full max-w-sm flex-col justify-center space-y-2'>
          <div className='flex flex-col space-y-2 text-start'>
            <h2 className='text-lg font-semibold tracking-tight'>Sign in</h2>
            <p className='text-sm text-muted-foreground'>
              Enter your username or user ID and password <br />
              to log into your account
            </p>
          </div>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className='grid gap-3'
            >
              <FormField
                control={form.control}
                name='username'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Username or User ID</FormLabel>
                    <FormControl>
                      <Input placeholder='Enter username or user ID' {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name='password'
                render={({ field }) => (
                  <FormItem className='relative'>
                    <FormLabel>Password</FormLabel>
                    <FormControl>
                      <PasswordInput placeholder='********' {...field} />
                    </FormControl>
                    <FormMessage />
                    <a
                      href='#'
                      className='absolute end-0 -top-0.5 text-sm font-medium text-muted-foreground hover:opacity-75'
                    >
                      Forgot password?
                    </a>
                  </FormItem>
                )}
              />
              <Button className='mt-2' disabled={isLoading}>
                {isLoading ? <Loader2 className='animate-spin' /> : <LogIn />}
                Sign in
              </Button>
            </form>
          </Form>
          <p className='px-8 text-center text-sm text-muted-foreground'>
            By clicking sign in, you agree to our{' '}
            <a
              href='/terms'
              className='underline underline-offset-4 hover:text-primary'
            >
              Terms of Service
            </a>{' '}
            and{' '}
            <a
              href='/privacy'
              className='underline underline-offset-4 hover:text-primary'
            >
              Privacy Policy
            </a>
            .
          </p>
        </div>
      </div>

      <div
        className={cn(
          'relative h-full overflow-hidden bg-muted max-lg:hidden flex items-center justify-center p-8'
        )}
      >
        <img
          src={DashboardPreview}
          alt='School Admin Dashboard Preview'
          className='max-h-full max-w-full object-contain select-none'
        />
      </div>
    </div>
  )
}
