import { useState, useEffect } from 'react'
import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  ArrowLeft,
  Bold,
  Italic,
  Underline as UnderlineIcon,
  List,
  ListOrdered,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Undo,
  Redo,
  Loader2,
} from 'lucide-react'
import { EmployeeSelector } from './employee-selector'

export function MemoEditor({ memo, onBack, selectedSchool }) {
  const [title, setTitle] = useState('')
  const [selectedEmployees, setSelectedEmployees] = useState([])
  const [isSubmitting, setIsSubmitting] = useState(false)

  const editor = useEditor({
    extensions: [
      StarterKit,
      Underline,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Placeholder.configure({
        placeholder: 'Write your memo content here...',
      }),
    ],
    content: '',
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none min-h-[400px] p-4',
      },
    },
  })

  useEffect(() => {
    if (memo && editor) {
      setTitle(memo.title)
      editor.commands.setContent(memo.content)
      setSelectedEmployees(memo.user_ids || [])
    }
  }, [memo, editor])

  const handleSave = async () => {
    if (!title.trim()) {
      toast.error('Please enter a title')
      return
    }

    if (!editor || editor.isEmpty) {
      toast.error('Please enter memo content')
      return
    }

    setIsSubmitting(true)

    try {
      const token = localStorage.getItem('token')
      const content = editor.getHTML()

      const url = memo
        ? `http://localhost:5000/api/admin/memos/${memo.id}`
        : 'http://localhost:5000/api/admin/memos/create'

      const method = memo ? 'PUT' : 'POST'

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
        body: JSON.stringify({
          schoolDbConfig: {
            db_name: selectedSchool.db_name,
            db_username: selectedSchool.db_username || 'root',
            db_password: selectedSchool.db_password || '',
          },
          title,
          content,
          user_ids: selectedEmployees,
        }),
      })

      const data = await response.json()

      if (response.ok) {
        toast.success(data.message || `Memo ${memo ? 'updated' : 'created'} successfully`)
        onBack()
      } else {
        toast.error(data.message || `Failed to ${memo ? 'update' : 'create'} memo`)
      }
    } catch (error) {
      console.error('Error saving memo:', error)
      toast.error('An error occurred while saving memo')
    } finally {
      setIsSubmitting(false)
    }
  }

  if (!editor) {
    return (
      <div className='flex h-[450px] items-center justify-center'>
        <Loader2 className='h-8 w-8 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <div className='space-y-6'>
      <div className='flex items-center justify-between'>
        <div className='flex items-center gap-4'>
          <Button variant='outline' size='sm' onClick={onBack}>
            <ArrowLeft className='mr-2 h-4 w-4' />
            Back to Memos
          </Button>
          <h2 className='text-2xl font-bold tracking-tight'>
            {memo ? 'Edit Memo' : 'Create New Memo'}
          </h2>
        </div>
        <Button onClick={handleSave} disabled={isSubmitting}>
          {isSubmitting ? (
            <>
              <Loader2 className='mr-2 h-4 w-4 animate-spin' />
              Saving...
            </>
          ) : (
            'Save Memo'
          )}
        </Button>
      </div>

      <div className='space-y-4'>
        <div>
          <Label htmlFor='title'>Title</Label>
          <Input
            id='title'
            placeholder='Enter memo title'
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className='mt-2'
          />
        </div>

        <EmployeeSelector
          selectedEmployees={selectedEmployees}
          onSelectionChange={setSelectedEmployees}
          selectedSchool={selectedSchool}
        />

        <div>
          <Label>Content</Label>
          <div className='mt-2 border rounded-lg overflow-hidden'>
            {/* Toolbar */}
            <div className='flex items-center gap-1 p-2 border-b bg-muted/50'>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().toggleBold().run()}
                className={editor.isActive('bold') ? 'bg-muted' : ''}
              >
                <Bold className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().toggleItalic().run()}
                className={editor.isActive('italic') ? 'bg-muted' : ''}
              >
                <Italic className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().toggleUnderline().run()}
                className={editor.isActive('underline') ? 'bg-muted' : ''}
              >
                <UnderlineIcon className='h-4 w-4' />
              </Button>

              <div className='w-px h-6 bg-border mx-1' />

              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                className={editor.isActive('bulletList') ? 'bg-muted' : ''}
              >
                <List className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                className={editor.isActive('orderedList') ? 'bg-muted' : ''}
              >
                <ListOrdered className='h-4 w-4' />
              </Button>

              <div className='w-px h-6 bg-border mx-1' />

              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().setTextAlign('left').run()}
                className={editor.isActive({ textAlign: 'left' }) ? 'bg-muted' : ''}
              >
                <AlignLeft className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().setTextAlign('center').run()}
                className={editor.isActive({ textAlign: 'center' }) ? 'bg-muted' : ''}
              >
                <AlignCenter className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().setTextAlign('right').run()}
                className={editor.isActive({ textAlign: 'right' }) ? 'bg-muted' : ''}
              >
                <AlignRight className='h-4 w-4' />
              </Button>

              <div className='w-px h-6 bg-border mx-1' />

              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().undo().run()}
                disabled={!editor.can().undo()}
              >
                <Undo className='h-4 w-4' />
              </Button>
              <Button
                variant='ghost'
                size='sm'
                onClick={() => editor.chain().focus().redo().run()}
                disabled={!editor.can().redo()}
              >
                <Redo className='h-4 w-4' />
              </Button>
            </div>

            {/* Editor */}
            <EditorContent editor={editor} className='bg-background' />
          </div>
        </div>
      </div>
    </div>
  )
}
