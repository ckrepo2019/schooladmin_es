import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { Toaster } from 'sonner'
import './index.css'
import { DialogsProvider } from '@/context/dialogs-provider'
import App from './App.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <DialogsProvider>
      <App />
      <Toaster richColors position='top-right' />
    </DialogsProvider>
  </StrictMode>,
)
