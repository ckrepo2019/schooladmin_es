import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'

export function MemoViewDialog({ memo, open, onClose }) {
  if (!memo) return null

  const userIds = memo.user_ids
  const isAllUsers = !userIds || userIds.length === 0

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className='max-w-3xl max-h-[80vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>{memo.title}</DialogTitle>
          <DialogDescription>
            <div className='flex items-center gap-2 mt-2'>
              <span>By {memo.creator_name}</span>
              <span className='text-muted-foreground'>{'\u2022'}</span>
              <span>{new Date(memo.created_at).toLocaleDateString()}</span>
              <span className='text-muted-foreground'>{'\u2022'}</span>
              <Badge variant={isAllUsers ? 'default' : 'secondary'} className='text-xs'>
                {isAllUsers ? 'All Employees' : `${userIds.length} Employees`}
              </Badge>
            </div>
          </DialogDescription>
        </DialogHeader>
        <div
          className='prose prose-sm max-w-none dark:prose-invert'
          dangerouslySetInnerHTML={{ __html: memo.content }}
        />
      </DialogContent>
    </Dialog>
  )
}

