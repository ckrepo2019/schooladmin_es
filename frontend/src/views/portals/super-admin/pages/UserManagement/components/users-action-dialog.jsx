import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
  FormDescription,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { useUsers } from './users-provider'

const userFormSchema = z.object({
  username: z.string().min(2, 'Username must be at least 2 characters'),
  school_ids: z.array(z.number()).optional(),
})

export function UsersActionDialog({ open, onSuccess }) {
  const { setOpen, currentRow } = useUsers()
  const isEdit = !!currentRow
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [schools, setSchools] = useState([])
  const [isLoadingSchools, setIsLoadingSchools] = useState(true)
  const [generatedCredentials, setGeneratedCredentials] = useState(null)

  const form = useForm({
    resolver: zodResolver(userFormSchema),
    defaultValues: {
      username: '',
      school_ids: [],
    },
  })

  // Fetch schools
  useEffect(() => {
    const fetchSchools = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await fetch('http://localhost:5000/api/super-admin/schools', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
          credentials: 'include',
        })

        const data = await response.json()

        if (response.ok) {
          setSchools(data.data || [])
        }
      } catch (error) {
        console.error('Error fetching schools:', error)
      } finally {
        setIsLoadingSchools(false)
      }
    }

    if (open) {
      fetchSchools()
    }
  }, [open])

  useEffect(() => {
    if (open && isEdit && currentRow) {
      form.reset({
        username: currentRow.username,
        school_ids: currentRow.schools?.map(s => s.id) || [],
      })
    } else if (open && !isEdit) {
      form.reset({
        username: '',
        school_ids: [],
      })
      setGeneratedCredentials(null)
    }
  }, [open, isEdit, currentRow, form])

  const onSubmit = async (data) => {
    setIsSubmitting(true)

    try {
      const token = localStorage.getItem('token')
      const url = isEdit
        ? `http://localhost:5000/api/super-admin/users/${currentRow.id}`
        : 'http://localhost:5000/api/super-admin/users'

      const response = await fetch(url, {
        method: isEdit ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
        body: JSON.stringify(data),
      })

      const result = await response.json()

      if (response.ok) {
        if (!isEdit) {
          // Show generated credentials
          setGeneratedCredentials({
            user_id: result.data.user_id,
            username: result.data.username,
            password: result.data.password,
          })
          toast.success('User created successfully! Please save the credentials.')
        } else {
          toast.success(result.message || 'User updated successfully')
          setOpen(null)
          form.reset()
          if (onSuccess) onSuccess()
        }
      } else {
        toast.error(result.message || `Failed to ${isEdit ? 'update' : 'create'} user`)
      }
    } catch (error) {
      toast.error('An error occurred. Please try again.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleCloseAfterCreate = () => {
    setOpen(null)
    form.reset()
    setGeneratedCredentials(null)
    if (onSuccess) onSuccess()
  }

  const handleOpenChange = (newOpen) => {
    if (!newOpen) {
      setOpen(null)
      form.reset()
      setGeneratedCredentials(null)
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='max-w-md max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit User' : 'Add User'}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? 'Update the user information and school assignments.'
              : 'Create a new user with school assignments.'}
          </DialogDescription>
        </DialogHeader>

        {generatedCredentials ? (
          <div className='space-y-4'>
            <div className='rounded-lg border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950 p-4 space-y-3'>
              <h3 className='font-semibold text-green-900 dark:text-green-100'>
                User Created Successfully!
              </h3>
              <p className='text-sm text-green-800 dark:text-green-200'>
                Please save these credentials. The password will not be shown again.
              </p>
              <div className='space-y-2 font-mono text-sm'>
                <div>
                  <span className='font-semibold'>User ID:</span>{' '}
                  <span className='text-green-900 dark:text-green-100'>{generatedCredentials.user_id}</span>
                </div>
                <div>
                  <span className='font-semibold'>Username:</span>{' '}
                  <span className='text-green-900 dark:text-green-100'>{generatedCredentials.username}</span>
                </div>
                <div>
                  <span className='font-semibold'>Password:</span>{' '}
                  <span className='text-green-900 dark:text-green-100'>{generatedCredentials.password}</span>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button onClick={handleCloseAfterCreate} className='w-full'>
                Close
              </Button>
            </DialogFooter>
          </div>
        ) : (
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-4'>
              <FormField
                control={form.control}
                name='username'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Username</FormLabel>
                    <FormControl>
                      <Input placeholder='Enter username' {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name='school_ids'
                render={() => (
                  <FormItem>
                    <div className='mb-4'>
                      <FormLabel className='text-base'>Assign Schools</FormLabel>
                      <FormDescription>
                        Select which schools this user can manage
                      </FormDescription>
                    </div>
                    {isLoadingSchools ? (
                      <div className='flex items-center justify-center py-4'>
                        <Loader2 className='h-6 w-6 animate-spin text-muted-foreground' />
                      </div>
                    ) : schools.length === 0 ? (
                      <div className='text-sm text-muted-foreground py-4'>
                        No schools available. Please create schools first.
                      </div>
                    ) : (
                      <div className='space-y-2 max-h-[200px] overflow-y-auto border rounded-md p-3'>
                        {schools.map((school) => (
                          <FormField
                            key={school.id}
                            control={form.control}
                            name='school_ids'
                            render={({ field }) => {
                              return (
                                <FormItem
                                  key={school.id}
                                  className='flex flex-row items-start space-x-3 space-y-0'
                                >
                                  <FormControl>
                                    <Checkbox
                                      checked={field.value?.includes(school.id)}
                                      onCheckedChange={(checked) => {
                                        return checked
                                          ? field.onChange([...field.value, school.id])
                                          : field.onChange(
                                              field.value?.filter(
                                                (value) => value !== school.id
                                              )
                                            )
                                      }}
                                    />
                                  </FormControl>
                                  <FormLabel className='font-normal cursor-pointer'>
                                    {school.school_name}
                                  </FormLabel>
                                </FormItem>
                              )
                            }}
                          />
                        ))}
                      </div>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />

              {!isEdit && (
                <div className='rounded-md bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 p-3'>
                  <p className='text-sm text-blue-900 dark:text-blue-100'>
                    <strong>Note:</strong> User ID and password will be automatically generated.
                    Default password is <span className='font-mono'>12345678</span>
                  </p>
                </div>
              )}

              <DialogFooter>
                <Button
                  type='button'
                  variant='outline'
                  onClick={() => handleOpenChange(false)}
                  disabled={isSubmitting}
                >
                  Cancel
                </Button>
                <Button type='submit' disabled={isSubmitting}>
                  {isSubmitting ? (
                    <>
                      <Loader2 className='mr-2 h-4 w-4 animate-spin' />
                      {isEdit ? 'Updating...' : 'Creating...'}
                    </>
                  ) : (
                    <>{isEdit ? 'Update User' : 'Create User'}</>
                  )}
                </Button>
              </DialogFooter>
            </form>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  )
}
