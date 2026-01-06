import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const DialogsContext = createContext(null)

const DEFAULT_CONFIRM_OPTIONS = {
  title: 'Are you sure?',
  description: '',
  confirmText: 'Continue',
  cancelText: 'Cancel',
  variant: 'default',
}

const DEFAULT_ALERT_OPTIONS = {
  title: 'Notice',
  description: '',
  actionText: 'OK',
  variant: 'default',
}

function normalizeOptions(input, defaults) {
  if (typeof input === 'string') return { ...defaults, description: input }
  if (input && typeof input === 'object') return { ...defaults, ...input }
  return { ...defaults }
}

function getAlertVariantStyles(variant) {
  switch (variant) {
    case 'success':
      return { Icon: CheckCircle2, iconClassName: 'text-emerald-600' }
    case 'warning':
      return { Icon: AlertTriangle, iconClassName: 'text-amber-600' }
    case 'error':
    case 'destructive':
      return { Icon: XCircle, iconClassName: 'text-destructive' }
    case 'info':
    default:
      return { Icon: Info, iconClassName: 'text-muted-foreground' }
  }
}

export function DialogsProvider({ children }) {
  const queueRef = useRef([])
  const activeRef = useRef(null)
  const nextIdRef = useRef(0)
  const [active, setActive] = useState(null)

  const setActiveDialog = useCallback((dialog) => {
    activeRef.current = dialog
    setActive(dialog)
  }, [])

  const enqueue = useCallback(
    (dialog) => {
      if (!activeRef.current) {
        setActiveDialog(dialog)
        return
      }
      queueRef.current.push(dialog)
    },
    [setActiveDialog]
  )

  const showNext = useCallback(() => {
    const next = queueRef.current.shift() || null
    setActiveDialog(next)
  }, [setActiveDialog])

  const finishActive = useCallback(
    (result, expectedId) => {
      const current = activeRef.current
      if (!current) return
      if (expectedId != null && current.id !== expectedId) return

      activeRef.current = null
      setActive(null)

      current.resolve(result)

      if (!activeRef.current && queueRef.current.length > 0) {
        showNext()
      }
    },
    [showNext]
  )

  const confirm = useCallback(
    (options) =>
      new Promise((resolve) => {
        enqueue({
          id: (nextIdRef.current += 1),
          kind: 'confirm',
          options: normalizeOptions(options, DEFAULT_CONFIRM_OPTIONS),
          resolve,
        })
      }),
    [enqueue]
  )

  const alert = useCallback(
    (options) =>
      new Promise((resolve) => {
        enqueue({
          id: (nextIdRef.current += 1),
          kind: 'alert',
          options: normalizeOptions(options, DEFAULT_ALERT_OPTIONS),
          resolve,
        })
      }),
    [enqueue]
  )

  const success = useCallback(
    (options) =>
      alert(
        normalizeOptions(options, {
          ...DEFAULT_ALERT_OPTIONS,
          title: 'Success',
          variant: 'success',
        })
      ),
    [alert]
  )

  const error = useCallback(
    (options) =>
      alert(
        normalizeOptions(options, {
          ...DEFAULT_ALERT_OPTIONS,
          title: 'Error',
          variant: 'destructive',
        })
      ),
    [alert]
  )

  const contextValue = useMemo(
    () => ({
      alert,
      confirm,
      error,
      success,
    }),
    [alert, confirm, error, success]
  )

  const isConfirmOpen = active?.kind === 'confirm'
  const isAlertOpen = active?.kind === 'alert'

  const confirmOptions = isConfirmOpen ? active?.options : null
  const alertOptions = isAlertOpen ? active?.options : null
  const confirmId = isConfirmOpen ? active?.id : null
  const alertId = isAlertOpen ? active?.id : null

  const { Icon: AlertIcon, iconClassName } = getAlertVariantStyles(alertOptions?.variant)
  const alertActionVariant =
    alertOptions?.variant === 'error' || alertOptions?.variant === 'destructive'
      ? 'destructive'
      : 'default'

  return (
    <DialogsContext.Provider value={contextValue}>
      {children}

      <AlertDialog open={isConfirmOpen}>
        <AlertDialogContent
          onEscapeKeyDown={(event) => event.preventDefault()}
          onPointerDownOutside={(event) => event.preventDefault()}
        >
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmOptions?.title}</AlertDialogTitle>
            {!!confirmOptions?.description && (
              <AlertDialogDescription>{confirmOptions.description}</AlertDialogDescription>
            )}
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => finishActive(false, confirmId)}>
              {confirmOptions?.cancelText || DEFAULT_CONFIRM_OPTIONS.cancelText}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => finishActive(true, confirmId)}
              className={cn(
                confirmOptions?.variant === 'destructive' &&
                  'bg-destructive text-destructive-foreground hover:bg-destructive/90'
              )}
            >
              {confirmOptions?.confirmText || DEFAULT_CONFIRM_OPTIONS.confirmText}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <Dialog open={isAlertOpen}>
        <DialogContent
          showCloseButton={false}
          onEscapeKeyDown={(event) => event.preventDefault()}
          onPointerDownOutside={(event) => event.preventDefault()}
        >
          <DialogHeader>
            <div className='flex items-start gap-3'>
              {AlertIcon && <AlertIcon className={cn('mt-0.5 h-5 w-5', iconClassName)} />}
              <div className='space-y-1'>
                <DialogTitle>{alertOptions?.title}</DialogTitle>
                {!!alertOptions?.description && (
                  <DialogDescription>{alertOptions.description}</DialogDescription>
                )}
              </div>
            </div>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant={alertActionVariant}
              onClick={() => finishActive(undefined, alertId)}
              autoFocus
            >
              {alertOptions?.actionText || DEFAULT_ALERT_OPTIONS.actionText}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DialogsContext.Provider>
  )
}

export const useDialogs = () => {
  const context = useContext(DialogsContext)
  if (!context) throw new Error('useDialogs must be used within DialogsProvider')
  return context
}
